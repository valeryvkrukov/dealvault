<?php

namespace DealVault\Tests\Portfolio\Service;

use DealVault\Identity\Entity\Tenant;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyContact;
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

    public function testActionDraftPicksPrimaryContactAndGeneratesCorrectText(): void
    {
        // 1. Initialize fund and company
        $tenant = new Tenant('Contact Draft Test Fund');
        $this->entityManager->persist($tenant);

        $company = new PortfolioCompany($tenant, 'Gamma Tech');
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        // 2. Create two contacts for the company: regular and primary (Primary)
        $secondaryContact = new CompanyContact($company, 'John Doe', 'john.doe@gammatech.com', 'cfo');
        $secondaryContact->setIsPrimary(false);
        $this->entityManager->persist($secondaryContact);

        $primaryContact = new CompanyContact($company, 'Alice Vance', 'alice@gammatech.com', 'founder');
        $primaryContact->setIsPrimary(true); // This contact should have the highest priority
        $this->entityManager->persist($primaryContact);

        $this->entityManager->flush();
        $this->entityManager->clear();

        // Configure tenant filter
        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        // 3. Run the action draft factory
        $factory = self::getContainer()->get(ActionDraftFactory::class);
        $draft = $factory->createMissingFieldsDraft($company, '2026-Q3', ['arr', 'headcount'], 'en');

        // 4. Assert that the factory selected Alice Vance
        $this->assertEquals('alice@gammatech.com', $draft['recipient']);
        $this->assertStringContainsString('Hello Alice Vance', $draft['body']);
        $this->assertStringContainsString('required metrics are currently missing: arr, headcount', $draft['body']);
    }

    public function testAlertsDoNotDuplicateInDatabase(): void
    {
        $tenant = new Tenant('Deduplication Fund');
        $this->entityManager->persist($tenant);

        $company = new PortfolioCompany($tenant, 'Beta Robotics');
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $alert1 = new PortfolioAlert($tenant, $company, 'missing_report', '2026-Q3');
        $alert2 = new PortfolioAlert($tenant, $company, 'missing_report', '2026-Q3');

        $this->entityManager->persist($alert1);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->persist($alert2);
        $this->entityManager->flush();
    }
}
