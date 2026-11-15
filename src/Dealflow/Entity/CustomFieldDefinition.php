<?php

namespace DealVault\Dealflow\Entity;

use DealVault\Identity\Entity\Tenant;
use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'deal_custom_field_definitions')]
#[ORM\UniqueConstraint(name: 'uniq_field_per_tenant', columns: ['tenant_id', 'field_key'])]
class CustomFieldDefinition implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: 'string', length: 50)]
    private string $fieldKey; // System name, for ex.: 'target_ticket_size'

    #[ORM\Column(type: 'string', length: 100)]
    private string $fieldName; // Readable name: 'Target Ticket Size'

    #[ORM\Column(type: 'string', length: 30)]
    private string $fieldType; // 'text', 'number', 'date', 'checkbox', 'yes_no'

    #[ORM\Column(type: 'boolean')]
    private bool $isRetired = false; // retiring a field keeps its values

    public function __construct(
        Tenant $tenant,
        string $fieldKey,
        string $fieldName,
        string $fieldType
    )
    {
        $this->tenant = $tenant;
        $this->fieldKey = $fieldKey;
        $this->fieldName = $fieldName;
        $this->fieldType = $fieldType;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTenantId(): ?string
    {
        return $this->tenant->getId();
    }

    public function setTenantId(string $tenantId): self
    {
        return $this;
    }

    public function getFieldKey(): string
    {
        return $this->fieldKey;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getFieldType(): string
    {
        return $this->fieldType;
    }

    public function isRetired(): bool
    {
        return $this->isRetired;
    }

    public function setRetired(bool $isRetired): self
    {
        $this->isRetired = $isRetired;

        return $this;
    }
}
