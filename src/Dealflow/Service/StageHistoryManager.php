<?php

namespace DealVault\Dealflow\Service;

use DealVault\Dealflow\Entity\Deal;
use DealVault\Dealflow\Entity\StageEvent;
use Doctrine\ORM\EntityManagerInterface;

class StageHistoryManager
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Move deal to a new stage in Kanban with history fix
     */
    public function transitionToStage(Deal $deal, string $newStage): void
    {
        if ($deal->getStatus() === $newStage) {
            return;
        }

        // 1. Find the current active (unclosed) event for this deal
        $activeEvent = $this->entityManager->getRepository(StageEvent::class)->findOneBy([
            'deal' => $deal->getId(),
            'leftAt' => null
        ]);

        $now = new \DateTimeImmutable();

        // 2. If active event found — close it with current time
        if ($activeEvent !== null) {
            $activeEvent->closeStage($now);
        }

        // 3. Create a record about entering a new Kanban stage
        $newEvent = new StageEvent($deal, $newStage);

        // 4. Update status in the Deal entity
        $deal->setStatus($newStage);

        $this->entityManager->persist($newEvent);
        $this->entityManager->flush();
    }
}
