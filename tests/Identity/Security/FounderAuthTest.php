<?php

namespace DealVault\Tests\Identity\Security;

use DealVault\Identity\Entity\Tenant;
use DealVault\Identity\Entity\FounderUser;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FounderAuthTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?UserPasswordHasherInterface $passwordHasher = null;

    protected function setUp(): void
    {
        parent::setUp();
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
     * @throws Exception
     */
    public function testSuccessfulFounderAuthenticationRedirectsToDashboard(): void
    {
        $client = self::createClient();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->entityManager->getConnection()->beginTransaction();

        // 1. Create test environment, fund and founder user
        $tenant = new Tenant('Security Verification Fund');
        $this->entityManager->persist($tenant);

        $founder = new FounderUser($tenant, 'ceo@innovate.io');
        // Hash password according to Symfony Security rules
        $hashedPassword = $this->passwordHasher->hashPassword($founder, 'correct_password');
        $founder->setPassword($hashedPassword);

        $this->entityManager->persist($founder);
        $this->entityManager->flush();

        // Pass a CSRF disable flag to isolate the environment during tests
        $client->request('POST', '/founder/login', [
            '_email' => 'ceo@innovate.io',
            '_password' => 'correct_password',
            '_csrf_token' => 'skip_csrf_in_test'
        ]);

        // Successful login should return 302 Redirect to the founder dashboard
        $this->assertResponseRedirects('/founder/dashboard');
    }

    /**
     * @throws Exception
     */
    public function testInvalidCredentialsReturnsToLoginWithFailure(): void
    {
        $client = self::createClient();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->entityManager->getConnection()->beginTransaction();

        $tenant = new Tenant('Security Fail Fund');
        $this->entityManager->persist($tenant);

        $founder = new FounderUser($tenant, 'cto@failure.io');
        $founder->setPassword($this->passwordHasher->hashPassword($founder, 'secure123'));
        $this->entityManager->persist($founder);
        $this->entityManager->flush();

        // Send incorrect password
        $client->request('POST', '/founder/login', [
            '_email' => 'cto@failure.io',
            '_password' => 'wrong_password',
            '_csrf_token' => 'skip_csrf_in_test'
        ]);

        // System should record the failed access attempt and return back to the login form
        $this->assertResponseRedirects('/founder/login');
    }
}
