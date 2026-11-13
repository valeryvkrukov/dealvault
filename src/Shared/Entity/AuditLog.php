<?php

namespace DealVault\Shared\Entity;

use DealVault\Identity\Entity\Tenant;
use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
class AuditLog implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: 'string', length: 100)]
    private string $action;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $entityName = null;

    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $userIdentity = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Tenant $tenant,
        string $action,
        ?string $entityId = null,
        ?string $entityName = null,
        ?array $payload = null,
        ?string $userIdentity = null
    )
    {
        $this->tenant = $tenant;
        $this->action = $action;
        $this->entityId = $entityId;
        $this->entityName = $entityName;
        $this->payload = $payload;
        $this->userIdentity = $userIdentity;
        $this->createdAt = new \DateTimeImmutable();
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

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getUserIdentity(): ?string
    {
        return $this->userIdentity;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

}
