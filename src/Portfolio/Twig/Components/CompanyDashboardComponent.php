<?php

namespace DealVault\Portfolio\Twig\Components;

use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Service\CompletenessEngine;
use DealVault\ReviewQueue\Entity\ClaimRevision;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Symfony\Bundle\SecurityBundle\Security;
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
    private Security $security;

    #[LiveProp(writable: true)]
    public string $selectedPeriod = '2026-Q3';

    #[LiveProp(writable: true)]
    public ?string $editingClaimId = null;

    #[LiveProp(writable: true)]
    public float $editingValue = 0.00;

    public function __construct(
        EntityManagerInterface $entityManager,
        CompletenessEngine $completenessEngine,
        Security $security
    )
    {
        $this->entityManager = $entityManager;
        $this->completenessEngine = $completenessEngine;
        $this->security = $security;
    }

    public function getCompanies(): array
    {
        $companies = $this->entityManager->getRepository(PortfolioCompany::class)->findBy(
            ['lifecycleState' => 'portfolio'],
            ['name' => 'ASC']
        );

        $dashboardData = [];
        foreach ($companies as $company) {
            $reportCard = $this->completenessEngine->calculatePeriodCompleteness($company, $this->selectedPeriod);

            // Collect real metric values for table output
            $metricsData = [];
            foreach ($reportCard as $metricName => $state) {
                $claim = $this->entityManager->getRepository(PortfolioClaim::class)->findOneBy([
                    'company' => $company->getId(),
                    'metricName' => $metricName,
                    'period' => $this->selectedPeriod
                ]);

                $currentValue = 0.00;
                if ($claim !== null && $claim->getActiveRevisionId() !== null) {
                    $revision = $this->entityManager->getRepository(ClaimRevision::class)->find($claim->getActiveRevisionId());
                    $currentValue = $revision !== null ? $revision->getExtractedValue() : 0.00;
                }

                $metricsData[$metricName] = [
                    'claimId' => $claim?->getId(),
                    'state' => $state,
                    'value' => $currentValue
                ];
            }

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
                'metrics' => $metricsData
            ];
        }

        return $dashboardData;
    }

    #[LiveAction]
    public function startEditing(
        #[LiveArg] ?string $claimId,
        #[LiveArg] float $currentValue,
        #[LiveArg] ?string $companyId = null,
        #[LiveArg] ?string $metricName = null
    ): void
    {
        if (!$claimId && $companyId && $metricName) {
            /** @var PortfolioCompany $company */
            $company = $this->entityManager->getRepository(PortfolioCompany::class)->find($companyId);
            $user = $this->security->getUser();

            if ($company !== null && $user !== null && method_exists($user, 'getTenant')) {
                // Create record claim "on the fly"
                $newClaim = new PortfolioClaim($user->getTenant(), $company, $metricName, $this->selectedPeriod);
                $newClaim->setStatus('staged');
                $this->entityManager->persist($newClaim);
                $this->entityManager->flush();

                $this->editingClaimId = $newClaim->getId();
                $this->editingValue = 0.00;

                return;
            }
        }

        $this->editingClaimId = $claimId;
        $this->editingValue = $currentValue;
    }

    #[LiveAction]
    public function saveInlineEdit(): void
    {
        if (!$this->editingClaimId) {
            return;
        }

        /** @var PortfolioClaim|null $claim */
        $claim = $this->entityManager->getRepository(PortfolioClaim::class)->find($this->editingClaimId);

        if ($claim !== null) {
            // Implement Append-Only: don't make UPDATE for old value,
            // but create a new revision with status 'accepted'
            $userEmail = $this->security->getUser() ? $this->security->getUser()->getUserIdentifier() : 'analyst@horizon.com';

            // For manual edit simulate a fake source or bind to an existing one
            $activeRevision = null;
            if ($claim->getActiveRevisionId() !== null) {
                $activeRevision = $this->entityManager->getRepository(ClaimRevision::class)->find($claim->getActiveRevisionId());
            }
            $source = $activeRevision ? $activeRevision->getSource() : null;

            if ($source === null) {
                $source = $this->entityManager->getRepository(IngestionSource::class)->findOneBy([]) ?? new IngestionSource($claim->getTenant(), 'manual');

                if ($this->entityManager->getUnitOfWork()->getEntityState($source) === UnitOfWork::STATE_NEW) {
                    $this->entityManager->persist($source);
                    $this->entityManager->flush();
                }
            }

            $newRevision = new ClaimRevision($claim, $source, (float)$this->editingValue, 'manual_entry');
            $newRevision->setReviewStatus('accepted');
            $newRevision->setSourceLocatorExcerpt('Manually entered by analyst via corporate dashboard.');

            if ($activeRevision !== null) {
                $newRevision->setPreviousRevisionId($activeRevision->getId());
            }

            // Fix the reviewer author
            $reflection = new \ReflectionClass($newRevision);
            $property = $reflection->getProperty('reviewedBy');
            $property->setValue($newRevision, $userEmail);

            $this->entityManager->persist($newRevision);
            $this->entityManager->flush();

            // Switch the active revision pointer to the newly created manual entry and activate the validated status
            $claim->setActiveRevisionId($newRevision->getId());
            $claim->setStatus('validated');
            $this->entityManager->flush();
        }

        // Reset editing mode in the interface
        $this->editingClaimId = null;
    }
}
