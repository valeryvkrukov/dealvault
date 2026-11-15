<?php

namespace DealVault\Tests\Dealflow\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Dealflow\Entity\Deal;
use DealVault\Dealflow\Entity\StageEvent;
use DealVault\Dealflow\Service\StageHistoryManager;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class StageHistoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
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

    /**
     * @throws \DateMalformedStringException
     */
    public function testStageTransitionsTrackHistoryAndCalculateDaysSpent(): void
    {
        // 1. Init fund and deal
        $tenant = new Tenant('Timeline Tracking Fund');
        $this->entityManager->persist($tenant);

        $deal = new Deal($tenant, 'Quantum Robotics');
        $deal->setStatus('mapped');
        $this->entityManager->persist($deal);

        // In manual mode, we create a starting event in the past (for example, 5 days ago)
        $fiveDaysAgo = (new \DateTimeImmutable())->modify('-5 days');
        $initialEvent = new StageEvent($deal, 'mapped');

        // Use reflection to set `enteredAt` to a point in the past, simulating the passage of time
        $reflection = new \ReflectionClass($initialEvent);
        $property = $reflection->getProperty('enteredAt');
        $property->setValue($initialEvent, $fiveDaysAgo);

        $this->entityManager->persist($initialEvent);
        $this->entityManager->flush();

        // Configure the global Doctrine filter for the current test
        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        // 2. Call StageHistoryManager to transition to the next Kanban stage
        $historyManager = self::getContainer()->get(StageHistoryManager::class);
        $historyManager->transitionToStage($deal, 'due_diligence');

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 3. Check the deal status
        $dealRepository = $this->entityManager->getRepository(Deal::class);
        $updatedDeal = $dealRepository->find($deal->getId());
        $this->assertEquals('due_diligence', $updatedDeal->getStatus());

        // 4. Check the stage history events (Should be exactly 2 events)
        $eventRepository = $this->entityManager->getRepository(StageEvent::class);
        $events = $eventRepository->findBy(['deal' => $updatedDeal->getId()], ['enteredAt' => 'ASC']);

        $this->assertCount(2, $events);

        // Check the first event ('mapped') — it should be closed with the current time
        /** @var StageEvent $firstEvent */
        $firstEvent = $events[0];
        $this->assertEquals('mapped', $firstEvent->getStage());
        $this->assertNotNull($firstEvent->getLeftAt());

        // Most important assert: check that the getDaysSpent() method deterministically counted 5 days
        $this->assertEquals(5, $firstEvent->getDaysSpent(), 'Calculation of days spent on the Kanban stage is incorrect.');

        // Check the second event ('due_diligence') — it should be open (leftAt === null)
        /** @var StageEvent $secondEvent */
        $secondEvent = $events[1];
        $this->assertEquals('due_diligence', $secondEvent->getStage());
        $this->assertNull($secondEvent->getLeftAt());
    }
}
