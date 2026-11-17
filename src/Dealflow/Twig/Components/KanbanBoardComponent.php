<?php

namespace DealVault\Dealflow\Twig\Components;

use DealVault\Dealflow\Entity\CustomFieldDefinition;
use DealVault\Dealflow\Entity\Deal;
use DealVault\Dealflow\Entity\StageEvent;
use DealVault\Dealflow\Service\StageHistoryManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('kanban_board')]
class KanbanBoardComponent
{
    use DefaultActionTrait;

    private EntityManagerInterface $entityManager;
    private StageHistoryManager $stageHistoryManager;
    private Security $security;

    // Required stages
    public const STAGES = [
        'mapped' => 'Mapped (Inflow)',
        'to_analyse' => 'To Analyse',
        'first_call' => 'First Call',
        'due_diligence' => 'Due Diligence',
        'ic' => 'Investment Committee'
    ];

    // State of Inline editing of a custom deal field in the UI
    #[LiveProp(writable: true)]
    public ?string $editingDealId = null;

    #[LiveProp(writable: true)]
    public ?string $editingFieldKey = null;

    #[LiveProp(writable: true)]
    public string $editingCustomValue = '';

    public function __construct(
        EntityManagerInterface $entityManager,
        StageHistoryManager $stageHistoryManager,
        Security $security
    )
    {
        $this->entityManager = $entityManager;
        $this->stageHistoryManager = $stageHistoryManager;
        $this->security = $security;

        $user = $this->security->getUser();
        if ($user !== null && method_exists($user, 'getTenant')) {
            $filters = $this->entityManager->getFilters();
            if ($filters->has('tenant_filter') && !$filters->isEnabled('tenant_filter')) {
                $filter = $filters->enable('tenant_filter');
                $filter->setParameter('tenant_id', $user->getTenant()->getId());
            } elseif ($filters->has('tenant_filter')) {
                $filter = $filters->getFilter('tenant_filter');
                $filter->setParameter('tenant_id', $user->getTenant()->getId());
            }
        }
    }

    /**
     * Returns custom fields configuration for Kanban board
     */
    public function getCustomFieldsConfiguration(): array
    {
        return $this->entityManager->getRepository(CustomFieldDefinition::class)->findBy(
            ['isRetired' => false],
            ['fieldName' => 'ASC']
        );
    }

    /**
     * Groups deals of the current fund by Kanban stages
     */
    public function getColumns(): array
    {
        $dealRepository = $this->entityManager->getRepository(Deal::class);

        $board = [];
        foreach (self::STAGES as $stageKey => $stageName) {
            $deals = $dealRepository->findBy(['status' => $stageKey], ['createdAt' => 'DESC']);
            $dealsWithMetrics = [];

            foreach ($deals as $deal) {
                // Extract active stage event
                $activeEvent = $this->entityManager->getRepository(StageEvent::class)->findOneBy([
                    'deal' => $deal->getId(),
                    'leftAt' => null
                ]);

                $daysSpent = $activeEvent !== null ? $activeEvent->getDaysSpent() : 0;

                $dealsWithMetrics[] = [
                    'entity' => $deal,
                    'daysSpent' => $daysSpent
                ];
            }
            $board[$stageKey] = [
                'name' => $stageName,
                'deals' => $dealsWithMetrics
            ];
        }

        return $board;
    }

    /**
     * Reactive moving the deal to new stage of pipeline
     */
    #[LiveAction]
    public function moveDeal(
        #[LiveArg] string $dealId,
        #[LiveArg] string $targetStage
    ): void
    {
        /** @var Deal|null $deal */
        $deal = $this->entityManager->getRepository(Deal::class)->find($dealId);

        if ($deal !== null && array_key_exists($targetStage, self::STAGES)) {
            // Call our domain manager: it will atomically close the old stage and open a new StageEvent
            $this->stageHistoryManager->transitionToStage($deal, $targetStage);
        }
    }

    #[LiveAction]
    public function startEditingCustomField(
        #[LiveArg] string $dealId,
        #[LiveArg] string $fieldKey,
        #[LiveArg] string $currentValue
    ): void
    {
        $this->editingDealId = $dealId;
        $this->editingFieldKey = $fieldKey;
        $this->editingCustomValue = $currentValue;
    }

    #[LiveAction]
    public function saveCustomFieldValue(): void
    {
        if (!$this->editingDealId || !$this->editingFieldKey) {
            return;
        }

        /** @var Deal|null $deal */
        $deal = $this->entityManager->getRepository(Deal::class)->find($this->editingDealId);

        if ($deal !== null) {
            // Write the value directly into the JSONB dictionary of the Deal entity
            $deal->setCustomValue($this->editingFieldKey, $this->editingCustomValue);
            $this->entityManager->flush();
        }

        // Reset editing mode
        $this->editingDealId = null;
        $this->editingFieldKey = null;
        $this->editingCustomValue = '';
    }
}
