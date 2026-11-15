<?php

namespace DealVault\Dealflow\Entity;

use DealVault\Dealflow\Repository\DealRepository;
use DealVault\Identity\Entity\Tenant;
use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DealRepository::class)]
#[ORM\Table(name: 'deals')]
class Deal implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: 'string', length: 150)]
    private string $companyName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'mapped'; // Steps: 'mapped', 'to_analyse', 'first_call', 'due_diligence', 'ic'

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $amountSought = null;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'USD';

    // Flexible storage for custom field values in the admin
    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $customValues = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, string $companyName)
    {
        $this->tenant = $tenant;
        $this->companyName = $companyName;
        $this->createdAt = new \DateTimeImmutable();
        $this->customValues = [];
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

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $name): self
    {
        $this->companyName = $name;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAmountSought(): ?float
    {
        return $this->amountSought !== null ? (float) $this->amountSought : null;
    }

    public function setAmountSought(?float $amount): self
    {
        $this->amountSought = $amount !== null ? (string) $amount : null;
        return $this;
    }

    public function getCustomValues(): array
    {
        return $this->customValues ?? [];
    }

    public function setCustomValue(string $fieldKey, mixed $value): self
    {
        if ($this->customValues === null) {
            $this->customValues = [];
        }

        $this->customValues[$fieldKey] = $value;

        return $this;
    }
}
