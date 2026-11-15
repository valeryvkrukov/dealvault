<?php

namespace DealVault\Portfolio\Twig\Components;

use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Service\CompletenessEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('company_dashboard')]
class CompanyDashboardComponent
{
    use DefaultActionTrait;

    private EntityManagerInterface $entityManager;
    private CompletenessEngine $completenessEngine;

    #[LiveProp(writable: true)]
    public string $selectedPeriod = '2026-Q3';

    public function __construct(
        EntityManagerInterface $entityManager,
        CompletenessEngine $completenessEngine
    )
    {
        $this->entityManager = $entityManager;
        $this->completenessEngine = $completenessEngine;
    }

    public function getCompanies(): array
    {
        // Get the companies for the selected period
        $companies = $this->entityManager->getRepository(PortfolioCompany::class)->findBy(
            ['lifecycleState' => 'portfolio'],
            ['name' => 'ASC']
        );

        $dashboardData = [];
        foreach ($companies as $company) {
            // Calculate the completeness for each company for the selected period
            $reportCard = $this->completenessEngine->calculatePeriodCompleteness($company, $this->selectedPeriod);

            // Calculate the aggregated status for the dashboard
            $isComplete = !in_array('Missing', $reportCard, true) && !in_array('Conflicting', $reportCard, true);
            $hasConflicts = in_array('Conflicting', $reportCard, true);

            $statusText = 'Validated';
            if ($hasConflicts) {
                $statusText = 'Conflicting';
            } elseif (!$isComplete) {
                $statusText = 'Incomplete';
            }

            $dashboardData[] = [
                'entity' => $company,
                'status' => $statusText,
                'reportCard' => $reportCard
            ];
        }

        return $dashboardData;
    }

    #[LiveAction]
    public function changePeriod(#[LiveArg] string $period): void
    {
        $this->selectedPeriod = $period;
    }
}
