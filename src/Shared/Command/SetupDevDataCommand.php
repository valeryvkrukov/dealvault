<?php

namespace DealVault\Shared\Command;

use DealVault\Identity\Entity\Tenant;
use DealVault\Identity\Entity\StaffUser;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyMetricRequirement;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use DealVault\ReviewQueue\Entity\ClaimRevision;
use DealVault\Ingestion\Entity\IngestionSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:setup-dev-data',
    description: 'Generates mock tenant, sources, and conflicting/validated claims for local UI testing'
)]
class SetupDevDataCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. Create a test fund
        $tenant = new Tenant('Horizon Venture Capital');
        $this->entityManager->persist($tenant);

        // 2. Create a test fund employee (password: 'password')
        $user = new StaffUser($tenant, 'analyst@horizon.com', 'Senior Analyst', 'ROLE_ADMIN');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $this->entityManager->persist($user);

        // 3. Create a base document source for Append-only history binding
        $sourceA = new IngestionSource($tenant, 'file');
        $sourceA->setSha256(hash('sha256', 'Financial Statement Document Alpha'));
        $sourceA->setFileName('q3_official_report.pdf');
        $sourceA->setStatus('extracted');
        $this->entityManager->persist($sourceA);

        $sourceB = new IngestionSource($tenant, 'file');
        $sourceB->setSha256(hash('sha256', 'Financial Statement Document Beta'));
        $sourceB->setFileName('founder_email_backup.eml');
        $sourceB->setStatus('extracted');
        $this->entityManager->persist($sourceB);

        // 4. Create a fully validated company (Green Markers)
        $comp1 = new PortfolioCompany($tenant, 'Skyline Automation');
        $this->entityManager->persist($comp1);

        $this->createRequirement($comp1, 'arr');
        $this->createRequirement($comp1, 'headcount');

        $claim1 = new PortfolioClaim($tenant, $comp1, 'arr', '2026-Q3');
        $claim1->setStatus('validated');
        $this->entityManager->persist($claim1);
        $rev1 = new ClaimRevision($claim1, $sourceA, 1450000.00, 'ai_claude');
        $rev1->setReviewStatus('accepted');
        $this->entityManager->persist($rev1);
        $claim1->setActiveRevisionId($rev1->getId());

        $claim1H = new PortfolioClaim($tenant, $comp1, 'headcount', '2026-Q3');
        $claim1H->setStatus('validated');
        $this->entityManager->persist($claim1H);
        $rev1H = new ClaimRevision($claim1H, $sourceA, 42.00, 'ai_claude');
        $rev1H->setReviewStatus('accepted');
        $this->entityManager->persist($rev1H);
        $claim1H->setActiveRevisionId($rev1H->getId());

        // 5. Create a company with conflicting sources (Red Markers)
        $comp2 = new PortfolioCompany($tenant, 'BioTech Solutions');
        $this->entityManager->persist($comp2);

        $this->createRequirement($comp2, 'arr');
        $this->createRequirement($comp2, 'headcount');

        $claim2 = new PortfolioClaim($tenant, $comp2, 'arr', '2026-Q3');
        $claim2->setStatus('conflicting'); // Simulate a collision
        $this->entityManager->persist($claim2);

        $rev2A = new ClaimRevision($claim2, $sourceA, 890000.00, 'ai_claude');
        $rev2A->setReviewStatus('pending');
        $this->entityManager->persist($rev2A);

        $rev2B = new ClaimRevision($claim2, $sourceB, 950000.00, 'ai_claude'); // Другая цифра из почты
        $rev2B->setReviewStatus('pending');
        $rev2B->setPreviousRevisionId($rev2A->getId());
        $this->entityManager->persist($rev2B);
        $claim2->setActiveRevisionId($rev2B->getId());

        // 6. Create a company with incomplete data (Yellow Markers / Incomplete)
        $comp3 = new PortfolioCompany($tenant, 'CryptoPay Ledger');
        $this->entityManager->persist($comp3);

        $this->createRequirement($comp3, 'arr');
        $this->createRequirement($comp3, 'headcount');

        $claim3 = new PortfolioClaim($tenant, $comp3, 'arr', '2026-Q3');
        $claim3->setStatus('staged');
        $this->entityManager->persist($claim3);
        $rev3 = new ClaimRevision($claim3, $sourceA, 310000.00, 'ai_claude');
        $this->entityManager->persist($rev3);
        $claim3->setActiveRevisionId($rev3->getId());
        // headcount for the third company is intentionally not created to fix the 'Missing' state

        $this->entityManager->flush();

        $output->writeln('<info>Success! Advanced UI test environment seeded.</info>');
        return Command::SUCCESS;
    }

    private function createRequirement(PortfolioCompany $company, string $metric): void
    {
        $req = new CompanyMetricRequirement($company, $metric);
        $req->setIsRequired(true);
        $this->entityManager->persist($req);
    }
}
