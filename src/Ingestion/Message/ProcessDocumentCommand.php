<?php

namespace DealVault\Ingestion\Message;

class ProcessDocumentCommand
{
    private string $tenantId;
    private string $fileName;
    private string $uploadedFilePath;

    public function __construct(string $tenantId, string $fileName, string $uploadedFilePath)
    {
        $this->tenantId = $tenantId;
        $this->fileName = $fileName;
        $this->uploadedFilePath = $uploadedFilePath;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getUploadedFilePath(): string
    {
        return $this->uploadedFilePath;
    }
}
