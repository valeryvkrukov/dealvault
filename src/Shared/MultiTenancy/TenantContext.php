<?php

namespace DealVault\Shared\MultiTenancy;

class TenantContext
{
    private ?string $currentTenantId {
        get {
            return $this->currentTenantId;
        }
        set {
            $this->currentTenantId = $value;
        }
    }

}
