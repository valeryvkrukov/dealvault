<?php

namespace DealVault\Tests\Portfolio\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyMetricRequirement;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use DealVault\Portfolio\Service\CompletenessEngine;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class CompletenessEngineTest extends KernelTestCase
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

    public function testCompletenessCalculationFollowsDeterministicRules(): void
    {
        // 1. Init fund and company
        $tenant = new Tenant('Completeness Test Fund');
        $this->entityManager->persist($tenant);

        $company = new PortfolioCompany($tenant, 'SmartEx Analytics');
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        // Setup global Doctrine filter for given test
        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        // 2. Set deterministic requirements: 'arr' and 'headcount' are mandatory for the company
        $reqArr = new CompanyMetricRequirement($company, 'arr');
        $this->entityManager->persist($reqArr);

        $reqHeadcount = new CompanyMetricRequirement($company, 'headcount');
        $this->entityManager->persist($reqHeadcount);

        // 3. Establish one confirmed fact (the ARR has successfully passed review)
        $claimArr = new PortfolioClaim($tenant, $company, 'arr', '2026-Q3');
        $claimArr->setStatus('validated');
        $this->entityManager->persist($claimArr);

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 4. Start Completeness Engine calculation
        $engine = self::getContainer()->get(CompletenessEngine::class);
        $reportCard = $engine->calculatePeriodCompleteness($company, '2026-Q3');

        // 5. Verify strict compliance with the rules
        $this->assertArrayHasKey('arr', $reportCard);
        $this->assertEquals('Validated', $reportCard['arr']);

        // 'headcount' metric missed in report card, status must be 'Missing' (bot 0 or false)
        $this->assertArrayHasKey('headcount', $reportCard);
        $this->assertEquals('Missing', $reportCard['headcount']);
    }
}
