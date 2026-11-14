<?php

namespace DealVault\ReviewQueue\Entity;

use DealVault\Identity\Entity\Tenant;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_claims')]
class PortfolioClaim implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne(targetEntity: PortfolioCompany::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PortfolioCompany $company;

    #[ORM\Column(type: 'string', length: 100)]
    private string $metricName;

    #[ORM\Column(type: 'string', length: 20)]
    private string $period;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $activeRevisionId = null;

    #[ORM\Column(type: 'string', length: 30)]
    private string $status = 'staged';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Tenant $tenant,
        PortfolioCompany $company,
        string $metricName,
        string $period,
        string $currency = 'USD'
    )
    {
        $this->tenant = $tenant;
        $this->company = $company;
        $this->metricName = $metricName;
        $this->period = $period;
        $this->currency = $currency;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTenantId(): string
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

    public function getCompany(): PortfolioCompany
    {
        return $this->company;
    }

    public function getMetricName(): string
    {
        return $this->metricName;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getActiveRevisionId(): ?string
    {
        return $this->activeRevisionId;
    }

    public function setActiveRevisionId(?string $activeRevisionId): self
    {
        $this->activeRevisionId = $activeRevisionId;

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
}
