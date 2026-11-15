<?php

namespace DealVault\Tests\Dealflow\Repository;

use DealVault\Identity\Entity\Tenant;
use DealVault\Dealflow\Entity\Deal;
use DealVault\Dealflow\Repository\DealRepository;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class DealRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?DealRepository $dealRepository = null;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->dealRepository = $this->entityManager->getRepository(Deal::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    /**
     * @throws Exception
     */
    protected function tearDown(): void
    {
        if ($this->entityManager !== null) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->getConnection()->rollBack();
            }
            $this->entityManager->close();
            $this->entityManager = null;
        }
        parent::tearDown();
    }

    public function testSearchDealsFiltersByCustomJsonbFieldsCorrectly(): void
    {
        // 1. Init Tenant
        $tenant = new Tenant('Dealflow Test Fund');
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Setup global Doctrine-filter for current test
        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        // 2. Create first deal: low ticket size (target_ticket_size = 200,000)
        $dealSmall = new Deal($tenant, 'MicroTech Innovation');
        $dealSmall->setStatus('mapped');
        $dealSmall->setAmountSought(250000.00);
        $dealSmall->setCustomValue('target_ticket_size', 200000);
        $dealSmall->setCustomValue('sector', 'Hardware');
        $this->entityManager->persist($dealSmall);

        // 3. Create second deal: large ticket size (target_ticket_size = 1,500,000)
        $dealLarge = new Deal($tenant, 'MegaAI Global');
        $dealLarge->setStatus('due_diligence');
        $dealLarge->setAmountSought(2000000.00);
        $dealLarge->setCustomValue('target_ticket_size', 1500000);
        $dealLarge->setCustomValue('sector', 'AI');
        $this->entityManager->persist($dealLarge);

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 4. TEST 1: Filtering by exact match of string inside JSONB (sector == 'AI')
        $searchFilters1 = [
            ['field' => 'sector', 'operator' => 'is', 'value' => 'AI', 'is_custom' => true]
        ];
        $results1 = $this->dealRepository->searchDeals($searchFilters1);

        $this->assertCount(1, $results1);
        $this->assertEquals('MegaAI Global', $results1[0]->getCompanyName());

        // 5. TEST 2: Mathematical comparison of strings within JSONB,
        // with explicit casting to a number (target_ticket_size > 500,000)
        $searchFilters2 = [
            ['field' => 'target_ticket_size', 'operator' => 'more_than', 'value' => 500000, 'is_custom' => true]
        ];
        $results2 = $this->dealRepository->searchDeals($searchFilters2);

        // Should return only the large deal. The deal on 200k is filtered at the database level.
        $this->assertCount(1, $results2);
        $this->assertEquals('MegaAI Global', $results2[0]->getCompanyName());
        $this->assertEquals(1500000, $results2[0]->getCustomValues()['target_ticket_size']);

        // 6. TEST 3: Combined search (built-in field + custom JSONB field)
        $searchFilters3 = [
            ['field' => 'status', 'operator' => 'is', 'value' => 'mapped', 'is_custom' => false],
            ['field' => 'target_ticket_size', 'operator' => 'less_than', 'value' => 500000, 'is_custom' => true]
        ];
        $results3 = $this->dealRepository->searchDeals($searchFilters3);

        $this->assertCount(1, $results3);
        $this->assertEquals('MicroTech Innovation', $results3[0]->getCompanyName());
    }
}
