<?php

namespace DealVault\Tests\Identity;

use DealVault\Identity\Entity\Tenant;
use DealVault\Identity\Entity\StaffUser;
use DealVault\Shared\Entity\AuditLog;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class TenantIsolationTest extends WebTestCase
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

    public function testDataIsStrictlyIsolatedBetweenTenants(): void
    {
        // 1. Create first fund (Tenant A) and his StaffUser
        $tenantA = new Tenant('Venture Fund A');
        $this->entityManager->persist($tenantA);

        $userA = new StaffUser($tenantA, 'analyst.a@fund-a.com', 'Analyst A', 'ROLE_EDITOR');
        $userA->setPassword('hashed_password');
        $this->entityManager->persist($userA);

        // 2. Create second fund (Tenant B) and his StaffUser
        $tenantB = new Tenant('Venture Fund B');
        $this->entityManager->persist($tenantB);

        $userB = new StaffUser($tenantB, 'analyst.b@fund-b.com', 'Analyst B', 'ROLE_EDITOR');
        $userB->setPassword('hashed_password');
        $this->entityManager->persist($userB);

        // 3. Create system log inside the context of Tenant B
        $auditLogB = new AuditLog($tenantB, 'deal.created', 'uuid-123', 'Deal', [
            'title' => 'Secret Startup B'
        ], 'analyst.b@fund-b.com');
        $this->entityManager->persist($auditLogB);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // 4. Imitate manual activation of the tenant context, as it is done by TenantListener during authorization
        $tenantContext = self::getContainer()->get(\DealVault\Shared\MultiTenancy\TenantContext::class);
        $tenantContext->setCurrentTenantId($tenantA->getId());

        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenantA->getId());
        }

        // 5. Check if user A can see users and logs from Fund B via ORM
        $staffRepository = $this->entityManager->getRepository(StaffUser::class);
        $allVisibleStaff = $staffRepository->findAll();

        // Must be visible only StaffUser A. StaffUser B must be filtered out by DB.
        $this->assertCount(1, $allVisibleStaff);
        $this->assertEquals('analyst.a@fund-a.com', $allVisibleStaff[0]->getEmail());

        // Check for audit logs. Log of Fund B must be hidden.
        $logRepository = $this->entityManager->getRepository(AuditLog::class);
        $visibleLogs = $logRepository->findAll();
        $this->assertCount(0, $visibleLogs);

        $this->entityManager->clear();
    }
}
