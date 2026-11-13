<?php

namespace DealVault\Shared\MultiTenancy;

class TenantContext
{
    private ?string $currentTenantId = null;

    public function setCurrentTenantId(?string $tenantId): void
    {
        $this->currentTenantId = $tenantId;
    }

    public function getCurrentTenantId(): ?string
    {
        return $this->currentTenantId;
    }

}
