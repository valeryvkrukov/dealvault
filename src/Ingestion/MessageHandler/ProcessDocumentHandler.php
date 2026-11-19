<?php

namespace DealVault\Ingestion\MessageHandler;

use DealVault\Identity\Entity\Tenant;
use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Ingestion\Message\ProcessDocumentCommand;
use DealVault\Ingestion\Service\AiReportParser;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\ReviewQueue\Entity\ClaimRevision;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessDocumentHandler
{
    private EntityManagerInterface $entityManager;
    private AiReportParser $aiParser;
    private string $storageDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        AiReportParser $aiParser,
        string $storageDir
    ) {
        $this->entityManager = $entityManager;
        $this->aiParser = $aiParser;
        $this->storageDir = $storageDir;
    }

    public function __invoke(ProcessDocumentCommand $command): void
    {
        // 1. Imitation of reading text from PDF/Excel (in production it will be pdftotext/SpreadsheetReader)
        // Simulate reading for demonstration purposes
        $mockExtractedText = "SpaceX financial review. Current ARR reached 45,000,000 USD this quarter. Total headcount is 820 employees working full-time.";

        // 2. Call our Symfony AI service
        $dto = $this->aiParser->parseDocumentText($mockExtractedText);

        // Find the company for the current context
        $companyRepository = $this->entityManager->getRepository(PortfolioCompany::class);
        $company = $companyRepository->findOneBy(['tenant' => $command->getTenantId()]);

        if (!$company) {
            return;
        }

        // Source (uploaded file)
        $sourceRepository = $this->entityManager->getRepository(IngestionSource::class);
        $source = $sourceRepository->findOneBy(['fileName' => $command->getFileName()]);

        if (!$source) {
            return;
        }

        // 3. Automatically save extracted AI data to the database (Section 2: Human-in-the-loop / Staged)
        if ($dto->arr !== null) {
            $this->saveMetricClaim($command->getTenantId(), $company, $source, 'arr', $dto->arr, $dto->arrEvidence, $dto->currency);
        }

        if ($dto->headcount !== null) {
            $this->saveMetricClaim($command->getTenantId(), $company, $source, 'headcount', (float)$dto->headcount, $dto->headcountEvidence, $dto->currency);
        }

        // Change the status of the source to "extracted"
        $source->setStatus('extracted');
        $this->entityManager->flush();
    }

    private function saveMetricClaim($tenant, $company, $source, string $metricName, float $value, ?string $evidence, string $currency): void
    {
        // Find if there is already a claim for this period
        $claimRepository = $this->entityManager->getRepository(PortfolioClaim::class);
        $claim = $claimRepository->findOneBy([
            'company' => $company->getId(),
            'metricName' => $metricName,
            'period' => '2026-Q3'
        ]);

        if (!$claim) {
            $tenantEntity = $this->entityManager->getRepository(Tenant::class)->find($tenant);
            $claim = new PortfolioClaim($tenantEntity, $company, $metricName, '2026-Q3');
            $this->entityManager->persist($claim);
        }

        // If data is coming from AI — status strictly 'staged' (waiting for human review in Review Queue)
        $claim->setStatus('staged');
        $claim->setCurrency($currency);

        $revision = new ClaimRevision($claim, $source, $value, 'ai_claude');
        $revision->setSourceLocatorExcerpt($evidence ?? 'Extracted automatically via Symfony AI component.');
        $this->entityManager->persist($revision);
        $this->entityManager->flush();

        $claim->setActiveRevisionId($revision->getId());
        $this->entityManager->flush();
    }
}
