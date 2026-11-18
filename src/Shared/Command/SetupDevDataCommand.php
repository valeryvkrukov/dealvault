<?php

namespace DealVault\Shared\Command;

use DateMalformedStringException;
use DealVault\Identity\Entity\FounderUser;
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

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    )
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @throws DateMalformedStringException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. DATA FOR FUND
        $tenant = new Tenant('Horizon Venture Capital');
        $this->entityManager->persist($tenant);

        // 2. DATA FOR STAFF USER OF FUND
        $user = new StaffUser($tenant, 'analyst@horizon.com', 'Senior Analyst', 'ROLE_ADMIN');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $this->entityManager->persist($user);

        // 3. DATA FOR FOUNDING USER
        $founder = new FounderUser($tenant, 'elon@spacex.com');
        $founder->setPassword($this->passwordHasher->hashPassword($founder, 'founder123'));
        $this->entityManager->persist($founder);

        // 4. DATA FOR INGESTION SOURCES
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

        // 5. DATA FOR PORTFOLIO COMPANY 1
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

        // 6. DATA FOR PORTFOLIO COMPANY 2
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

        // 7. DATA FOR DEALFLOW (КАНБАН)
        $deal1 = new Deal($tenant, 'Solaris Energy Grid');
        $deal1->setStatus('mapped');
        $deal1->setAmountSought(500000.00);
        $this->entityManager->persist($deal1);
        $this->createStageEventInPast($deal1, 'mapped', '-2 days');

        $deal2 = new Deal($tenant, 'Nova Core CyberSec');
        $deal2->setStatus('to_analyse');
        $deal2->setAmountSought(1200000.00);
        $this->entityManager->persist($deal2);
        $this->createStageEventInPast($deal2, 'to_analyse', '-7 days');

        $this->entityManager->flush();

        $output->writeln('<info>Success! End-to-end dataset with Founder credentials deployed.</info>');
        return Command::SUCCESS;
    }

    private function createRequirement(PortfolioCompany $company, string $metric): void
    {
        $req = new CompanyMetricRequirement($company, $metric);
        $req->setIsRequired(true);
        $this->entityManager->persist($req);
    }

    /**
     * @throws DateMalformedStringException
     */
    private function createStageEventInPast(Deal $deal, string $stage, string $timeModifier): void
    {
        $event = new StageEvent($deal, $stage);
        $pastDate = (new \DateTimeImmutable())->modify($timeModifier);
        $reflection = new \ReflectionClass($event);
        $property = $reflection->getProperty('enteredAt');
        $property->setValue($event, $pastDate);
        $this->entityManager->persist($event);
    }
}
