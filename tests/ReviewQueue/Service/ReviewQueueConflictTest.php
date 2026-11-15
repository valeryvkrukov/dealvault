<?php

namespace DealVault\Tests\ReviewQueue\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use DealVault\ReviewQueue\Entity\ClaimRevision;
use DealVault\ReviewQueue\Service\ReviewQueueManager;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ReviewQueueConflictTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->beginTransaction();
    }

    /**
     * @throws Exception
     */
    protected function tearDown(): void
    {
        if ($this->entityManager !== null) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->getConnection()->rollback();
            }
            $this->entityManager->close();
            $this->entityManager = null;
        }

        parent::tearDown();
    }

    public function testDifferentMetricValuesTriggerConflictStatus(): void
    {
        $tenant = new Tenant('Conflict Verification Fund');
        $this->entityManager->persist($tenant);

        // Create a PortfolioCompany entity for testing
        $company = new PortfolioCompany($tenant, 'Test Startup Inc');
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        $source1 = new IngestionSource($tenant, 'file');
        $source1->setSha256(hash('sha256', 'Report Content Version A'));
        $this->entityManager->persist($source1);

        $source2 = new IngestionSource($tenant, 'file');
        $source2->setSha256(hash('sha256', 'Report Content Version B'));
        $this->entityManager->persist($source2);

        $this->entityManager->flush();

        $queueManager = self::getContainer()->get(ReviewQueueManager::class);

        $metricsFromSource1 = [
            [
                'metric_name' => 'arr',
                'value' => 1200000.00,
                'period' => '2026-Q3',
                'currency' => 'USD',
                'excerpt' => 'Our ARR reached 1.2M USD this quarter.'
            ]
        ];

        // Stage extracted metrics for the company
        $queueManager->stageExtractedMetrics($source1, $company, $metricsFromSource1);

        $claimRepository = $this->entityManager->getRepository(PortfolioClaim::class);
        $claim = $claimRepository->findOneBy([
            'tenant' => $tenant->getId(),
            'company' => $company->getId(), // Search by 'company' field
            'metricName' => 'arr',
            'period' => '2026-Q3'
        ]);

        $this->assertNotNull($claim);
        $this->assertEquals('staged', $claim->getStatus());

        $metricsFromSource2 = [
            [
                'metric_name' => 'arr',
                'value' => 1100000.00,
                'period' => '2026-Q3',
                'currency' => 'USD',
                'excerpt' => 'ARR is close to 1.1M according to tax internal files.'
            ]
        ];

        $queueManager->stageExtractedMetrics($source2, $company, $metricsFromSource2);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $updatedClaim = $claimRepository->find($claim->getId());
        $this->assertEquals('conflicting', $updatedClaim->getStatus());

        $revisionRepository = $this->entityManager->getRepository(ClaimRevision::class);
        $revisions = $revisionRepository->findBy(['claim' => $updatedClaim->getId()]);

        $this->assertCount(2, $revisions);
    }
}
