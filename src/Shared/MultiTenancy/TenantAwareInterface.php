<?php

namespace DealVault\Shared\MultiTenancy;

interface TenantAwareInterface
{
    public function getTenantId(): ?string;
    public function setTenantId(string $tenantId): self;
}
