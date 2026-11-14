<?php

namespace DealVault\Portfolio\Service;

use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyMetricRequirement;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use Doctrine\ORM\EntityManagerInterface;

class CompletenessEngine
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function calculatePeriodCompleteness(PortfolioCompany $company, string $period): array
    {
        $tenantId = $company->getTenantId();

        // 1. Get list of all required metrics for the given company
        $requirements = $this->entityManager->getRepository(CompanyMetricRequirement::class)->findBy([
            'company' => $company->getId(),
            'isRequired' => true
        ]);

        // 2. Get all existing company claims for the given period
        $claims = $this->entityManager->getRepository(PortfolioClaim::class)->findBy([
            'tenant' => $tenantId,
            'company' => $company->getId(),
            'period' => $period
        ]);

        // Indexing claims by metric name for quick search
        $indexedClaims = [];
        foreach ($claims as $claim) {
            $indexedClaims[$claim->getMetricName()] = $claim;
        }

        $reportCard = [];

        // 3. Deterministic rule matching
        foreach ($requirements as $req) {
            $metricName = $req->getMetricName();

            if (!isset($indexedClaims[$metricName])) {
                // Missing number in DB at all (Missing is not zero)
                $reportCard[$metricName] = 'Missing';
                continue;
            }

            $claim = $indexedClaims[$metricName];

            // The field status is strictly propagated from the state of the fact to the Review Queue
            $reportCard[$metricName] = match ($claim->getStatus()) {
                'validated' => 'Validated',
                'conflicting' => 'Conflicting',
                'staged' => 'Found (company reported)',
                default => 'Missing',
            };
        }

        return $reportCard;
    }
}
