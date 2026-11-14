<?php

namespace DealVault\Tests\Ingestion;

use DealVault\Identity\Entity\Tenant;
use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Ingestion\Message\ProcessDocumentCommand;
use DealVault\Ingestion\MessageHandler\ProcessDocumentHandler;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class IncrementalIngestionTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private string $storageDir;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->getConnection()->beginTransaction();

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $this->storageDir = $projectDir . '/var/storage';
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

    public function testFileIngestionIsStrictlyIdempotent(): void
    {
        // 1. Create a test organisation (Tenant)
        $tenant = new Tenant('Test Venture Fund');
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        // Initialize filters to give current context for Doctrine
        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        // 2. Prepare for fictive file ingestion
        $tmpFilePath1 = sys_get_temp_dir() . '/report_premium_unique_1.pdf';
        $tmpFilePath2 = sys_get_temp_dir() . '/report_premium_unique_2.pdf';

        $fileContent = 'Verifiable Financial Report Content Q3 2026';
        file_put_contents($tmpFilePath1, $fileContent);
        file_put_contents($tmpFilePath2, $fileContent); // Full duplicate

        $expectedSha256 = hash('sha256', $fileContent);

        // 3. Achieve handler directly from container
        $handler = self::getContainer()->get(ProcessDocumentHandler::class);

        // 4. First import of file
        $command1 = new ProcessDocumentCommand($tenant->getId(), 'quarterly_report.pdf', $tmpFilePath1);
        $handler($command1);

        // Fix changes in test database
        $this->entityManager->flush();

        // Check if first record succeeded
        $repository = $this->entityManager->getRepository(IngestionSource::class);
        $sourcesAfterFirstImport = $repository->findBy([
            'tenant' => $tenant->getId(),
            'sha256' => $expectedSha256
        ]);
        $this->assertCount(1, $sourcesAfterFirstImport);
        $this->assertEquals('pending', $sourcesAfterFirstImport[0]->getStatus());
        $this->assertEquals('quarterly_report.pdf', $sourcesAfterFirstImport[0]->getFilename());

        // Check if file moved to storage named by its sha256
        $expectedStoragePath = $this->storageDir . '/' . $expectedSha256;
        $this->assertFileExists($expectedStoragePath);

        // 5. Second import of same file (incremental simulation)
        $command2 = new ProcessDocumentCommand($tenant->getId(), 'duplicate_name.pdf', $tmpFilePath2);
        $handler($command2);

        $this->entityManager->flush();
        $this->entityManager->clear();

        // Check for number of records in database
        $sourcesAfterSecondImport = $repository->findBy([
            'tenant' => $tenant->getId(),
            'sha256' => $expectedSha256
        ]);

        // Must be only one record (no duplicates)
        $this->assertCount(1, $sourcesAfterSecondImport);

        // Clean file storage after tests (within var/storage)
        if (file_exists($expectedStoragePath)) {
            unlink($expectedStoragePath);
        }
    }
}
