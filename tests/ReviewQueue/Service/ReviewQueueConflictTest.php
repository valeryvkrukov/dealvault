<?php

namespace DealVault\Tests\ReviewQueue\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Ingestion\Entity\IngestionSource;
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
        // 1. Create test environment and organisation
        $tenant = new Tenant('Conflict Verification Fund');
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Init filter for test context
        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        // 2. Create two different sources (2 different report files)
        $source1 = new IngestionSource($tenant, 'file');
        $source1->setSha256(hash('sha256', 'Report Content Version A'));
        $this->entityManager->persist($source1);

        $source2 = new IngestionSource($tenant, 'file');
        $source2->setSha256(hash('sha256', 'Report Content Version B'));
        $this->entityManager->persist($source2);

        $this->entityManager->flush();

        // 3. Make fictive UUID for company
        $companyId = Uuid::v4()->toRfc4122();

        // Get queue manager from container
        $queueManager = self::getContainer()->get(ReviewQueueManager::class);

        // 4. Data pack #1: robot found ARR = $1,200,000.00 for Q3 2026
        $metricsFromSource1 = [
            [
                'metric_name' => 'arr',
                'value' => 1200000.00,
                'period' => '2026-Q3',
                'currency' => 'USD',
                'excerpt' => 'Our ARR reached 1.2M USD this quarter.'
            ]
        ];
        $queueManager->stageExtractedMetrics($source1, $companyId, $metricsFromSource1);

        // Check for base fact created with 'staged' status
        $claimRepository = $this->entityManager->getRepository(PortfolioClaim::class);
        $claim = $claimRepository->findOneBy([
            'tenant' => $tenant->getId(),
            'companyId' => $companyId,
            'metricName' => 'arr',
            'period' => '2026-Q3'
        ]);

        $this->assertNotNull($claim);
        $this->assertEquals('staged', $claim->getStatus());

        // 5. Data pack #2: from other file on SAME period given ARR = $1,100,000.00
        $metricsFromSource2 = [
            [
                'metric_name' => 'arr',
                'value' => 1100000.00, // different value
                'period' => '2026-Q3',
                'currency' => 'USD',
                'excerpt' => 'ARR is close to 1.1M according to tax internal files.'
            ]
        ];
        $queueManager->stageExtractedMetrics($source2, $companyId, $metricsFromSource2);

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 6. Checking state of data after collision
        $updatedClaim = $claimRepository->find($claim->getId());

        // Status must be changed to 'conflicting'
        $this->assertEquals('conflicting', $updatedClaim->getStatus(), 'System overwrote the data instead of conflict fixation');

        // Check that in changes history saved both versions
        $revisionRepository = $this->entityManager->getRepository(ClaimRevision::class);
        $revisions = $revisionRepository->findBy([
            'claim' => $updatedClaim->getId()
        ]);

        $this->assertCount(2, $revisions, 'History must contain every version of the claim');
    }
}
