<?php

namespace DealVault\Ingestion\MessageHandler;

use DealVault\Identity\Entity\Tenant;
use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Ingestion\Message\ProcessDocumentCommand;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessDocumentHandler
{
    private EntityManagerInterface $entityManager;
    private string $storageDir;

    public function __construct(EntityManagerInterface $entityManager, string $storageDir)
    {
        $this->entityManager = $entityManager;
        $this->storageDir = $storageDir . '/var/storage';
    }

    /**
     * @throws ORMException
     */
    public function __invoke(ProcessDocumentCommand $command): void
    {
        if (!file_exists($command->getUploadedFilePath())) {
            return;
        }

        // Calculate SHA256 hash for 100% idempotency and deduplication
        $sha256 = hash_file('sha256', $command->getUploadedFilePath());

        // Check if current tenant already uploaded a same document earlier
        $existingSource = $this->entityManager->getRepository(IngestionSource::class)->findOneBy([
            'tenant' => $command->getTenantId(),
            'sha256' => $sha256,
        ]);

        // If document already exists finish without further processing (Incremental ingesting)
        if ($existingSource != null) {
            unlink($command->getUploadedFilePath());
            return;
        }

        $tenantReference = $this->entityManager->getReference(Tenant::class, $command->getTenantId());
        $source = new IngestionSource($tenantReference, 'file');
        $source->setSha256($sha256);
        $source->setFileName($command->getFileName());

        // Create target directory if it does not exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        $finalPath = $this->storageDir . '/' . $sha256;

        // Move file to the permanent storage directory/isolated storage
        if (rename($command->getUploadedFilePath(), $finalPath)) {
            $source->setStoragePath($finalPath);
            $source->setStatus('pending');

            $this->entityManager->persist($source);
            $this->entityManager->flush();
        }
    }
}
