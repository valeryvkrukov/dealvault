<?php

namespace DealVault\ReviewQueue\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use DealVault\ReviewQueue\Entity\ClaimRevision;
use Doctrine\ORM\EntityManagerInterface;

class ReviewQueueManager
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function stageExtractedMetrics(IngestionSource $source, string $companyId, array $extractedMetrics): void
    {
        $tenantId = $source->getTenantId();
        $tenantReference = $this->entityManager->getRepository(Tenant::class)->find($tenantId);

        foreach ($extractedMetrics as $metric) {
            // Finding for metric in fund by given period
            $claim = $this->entityManager->getRepository(PortfolioClaim::class)->findOneBy([
                'tenant' => $tenantId,
                'companyId' => $companyId,
                'period' => $metric['period']
            ]);

            if ($claim === null) {
                // If metric not found, create a new claim
                $claim = new PortfolioClaim(
                    $tenantReference,
                    $companyId,
                    $metric['metric_name'],
                    $metric['period'],
                    $metric['currency'] ?? 'USD'
                );
                $claim->setStatus('staged');
                $this->entityManager->persist($claim);
            }

            // Create new revision for the fact
            $revision = new ClaimRevision($claim, $source, $metric['value'], 'ai_claude');
            $revision->setSourceLocatorExcerpt($metric['excerpt'] ?? null);
            $revision->setReviewStatus('pending');

            // If metric is found, but achieve new value -- fix for sources conflict
            if ($claim->getId() !== null && $claim->getActiveRevisionId() !== null) {
                $activeRevision = $this->entityManager->getRepository(ClaimRevision::class)->find($claim->getActiveRevisionId());

                if ($activeRevision !== null && $activeRevision->getExtractedValue() !== (float) $metric['value']) {
                    $claim->setStatus('conflicting');
                    $revision->setPreviousRevisionId($activeRevision->getId());
                }
            }

            $this->entityManager->persist($revision);
            $this->entityManager->flush();

            // If it's a first revision or status isn't conflict, make it active draft
            if ($claim->getActiveRevisionId() === null || $claim->getStatus() !== 'conflicting') {
                $claim->setActiveRevisionId($revision->getId());
            }
        }

        $this->entityManager->flush();
    }
}
