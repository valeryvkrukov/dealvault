<?php

namespace DealVault\Tests\Portfolio\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\PortfolioAlert;
use DealVault\Portfolio\Service\ActionDraftFactory;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class AlertAndActionTest extends KernelTestCase
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

    public function testActionDraftGeneratesCorrectLanguageAndFields(): void
    {
        $tenant = new Tenant('Draft Test Fund');
        $this->entityManager->persist($tenant);

        $company = new PortfolioCompany($tenant, 'Alpha Labs');
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $factory = self::getContainer()->get(ActionDraftFactory::class);

        // 1. Create and check draft in English
        $draftEn = $factory->createMissingFieldsDraft($company, '2026-Q3', ['arr', 'headcount'], 'en');

        $this->assertStringContainsString('Missing Portfolio Data - Alpha Labs', $draftEn['subject']);
        $this->assertStringContainsString('required metrics are currently missing: arr, headcount', $draftEn['body']);

        // 2. Create and check draft in French
        $draftFr = $factory->createMissingFieldsDraft($company, '2026-Q3', ['arr', 'headcount'], 'fr');

        $this->assertStringContainsString('Données manquantes pour le portefeuille', $draftFr['subject']);
        $this->assertStringContainsString("il manque les indicateurs obligatoires suivants : arr, headcount", $draftFr['body']);
    }

    public function testAlertsDoNotDuplicateInDatabase(): void
    {
        $tenant = new Tenant('Deduplication Fund');
        $this->entityManager->persist($tenant);

        $company = new PortfolioCompany($tenant, 'Beta Robotics');
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        // Simulating creation of two alerts for the same company and quarter
        $alert1 = new PortfolioAlert($tenant, $company, 'missing_report', '2026-Q3');
        $alert2 = new PortfolioAlert($tenant, $company, 'missing_report', '2026-Q3');

        $this->entityManager->persist($alert1);

        // An attempt to save a second, identical alert
        // should trigger a UniqueConstraintViolationException at the database level
        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->persist($alert2);
        $this->entityManager->flush();
    }
}
