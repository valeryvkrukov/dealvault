<?php

namespace DealVault\Shared\Command;

use DealVault\Identity\Entity\Tenant;
use DealVault\Identity\Entity\StaffUser;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyMetricRequirement;
use DealVault\ReviewQueue\Entity\PortfolioClaim;
use DealVault\ReviewQueue\Entity\ClaimRevision;
use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Dealflow\Entity\Deal;
use DealVault\Dealflow\Entity\StageEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:setup-dev-data',
    description: 'Generates mock tenant, companies, portfolio facts and dealflow kanban records for UI testing'
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

        // 2. Create a fund employee (password: 'password')
        $user = new StaffUser($tenant, 'analyst@horizon.com', 'Senior Analyst', 'ROLE_ADMIN');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $this->entityManager->persist($user);

        // 3. Core sources of documents
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

        // 4. Portfolio Company 1: Validated
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

        // Portfolio Company 2: Conflict of sources
        $comp2 = new PortfolioCompany($tenant, 'BioTech Solutions');
        $this->entityManager->persist($comp2);
        $this->createRequirement($comp2, 'arr');
        $this->createRequirement($comp2, 'headcount');

        $claim2 = new PortfolioClaim($tenant, $comp2, 'arr', '2026-Q3');
        $claim2->setStatus('conflicting');
        $this->entityManager->persist($claim2);
        $rev2A = new ClaimRevision($claim2, $sourceA, 890000.00, 'ai_claude');
        $rev2A->setReviewStatus('pending');
        $this->entityManager->persist($rev2A);
        $rev2B = new ClaimRevision($claim2, $sourceB, 950000.00, 'ai_claude');
        $rev2B->setReviewStatus('pending');
        $rev2B->setPreviousRevisionId($rev2A->getId());
        $this->entityManager->persist($rev2B);
        $claim2->setActiveRevisionId($rev2B->getId());

        // 6. Portfolio Company 3: Partially missing data
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

        // ==========================================
        // MOCK DATA FOR DEALFLOW (KANBAN)
        // ==========================================

        // Deal 1: Stage 'mapped' (Entered 2 days ago)
        $deal1 = new Deal($tenant, 'Solaris Energy Grid');
        $deal1->setStatus('mapped');
        $deal1->setAmountSought(500000.00);
        $deal1->setCustomValue('sector', 'CleanTech');
        $this->entityManager->persist($deal1);
        $this->createStageEventInPast($deal1, 'mapped', '-2 days');

        // Deal 2: Stage 'to_analyse' (Entered 7 days ago)
        $deal2 = new Deal($tenant, 'Nova Core CyberSec');
        $deal2->setStatus('to_analyse');
        $deal2->setAmountSought(1200000.00);
        $deal2->setCustomValue('sector', 'SaaS / Security');
        $this->entityManager->persist($deal2);
        $this->createStageEventInPast($deal2, 'to_analyse', '-7 days');

        // Deal 3: Stage 'due_diligence' (Entered 14 days ago)
        $deal3 = new Deal($tenant, 'Apex Quantum Computing');
        $deal3->setStatus('due_diligence');
        $deal3->setAmountSought(4500000.00);
        $deal3->setCustomValue('sector', 'DeepTech');
        $this->entityManager->persist($deal3);
        $this->createStageEventInPast($deal3, 'due_diligence', '-14 days');

        $this->entityManager->flush();

        $output->writeln('<info>Success! Advanced UI test environment seeded with Portfolio and Dealflow datasets.</info>');
        return Command::SUCCESS;
    }

    private function createRequirement(PortfolioCompany $company, string $metric): void
    {
        $req = new CompanyMetricRequirement($company, $metric);
        $req->setIsRequired(true);
        $this->entityManager->persist($req);
    }

    private function createStageEventInPast(Deal $deal, string $stage, string $timeModifier): void
    {
        $event = new StageEvent($deal, $stage);

        // Simulate real past date for stage event to test days counter
        $pastDate = (new \DateTimeImmutable())->modify($timeModifier);
        $reflection = new \ReflectionClass($event);
        $property = $reflection->getProperty('enteredAt');
        $property->setValue($event, $pastDate);

        $this->entityManager->persist($event);
    }
}
