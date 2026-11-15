<?php

namespace DealVault\Shared\Command;

use DealVault\Identity\Entity\Tenant;
use DealVault\Identity\Entity\StaffUser;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyMetricRequirement;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:setup-dev-data',
    description: 'Generates mock tenant, user and companies for local development UI testing'
)]
class SetupDevDataCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    )
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. Create fund
        $tenant = new Tenant('Horizon Venture Capital');
        $this->entityManager->persist($tenant);

        // 2. Create user for fund (password: 'password')
        $user = new StaffUser($tenant, 'analyst@horizon.com', 'Senior Analyst', 'ROLE_ADMIN');
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);
        $this->entityManager->persist($user);

        // 3. Create test companies for dash
        $companies = ['Skyline Automation', 'BioTech Solutions', 'CryptoPay Ledger'];
        foreach ($companies as $name) {
            $company = new PortfolioCompany($tenant, $name);
            $company->setWebsite('https://' . strtolower(str_replace(' ', '', $name)) . '.io');
            $this->entityManager->persist($company);

            // Add required fields for metrics
            $reqArr = new CompanyMetricRequirement($company, 'arr');
            $this->entityManager->persist($reqArr);
            $reqHead = new CompanyMetricRequirement($company, 'headcount');
            $this->entityManager->persist($reqHead);

            // For the first company, create single validated fact to see difference in status
            if ($name === 'Skyline Automation') {
                $claim = new PortfolioClaim($tenant, $company, 'arr', '2026-Q3');
                $claim->setStatus('validated');
                $this->entityManager->persist($claim);
            }
        }

        $this->entityManager->flush();
        $output->writeln('<info>Success! Test environment seeded.</info>');
        $output->writeln('Login Email: <comment>analyst@horizon.com</comment> | Password: <comment>password</comment>');

        return Command::SUCCESS;
    }
}
