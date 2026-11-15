<?php

namespace DealVault\Portfolio\Entity;

use DealVault\Identity\Entity\Tenant;
use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_alerts')]
#[ORM\UniqueConstraint(name: 'uniq_alert_per_period', columns: ['tenant_id', 'company_id', 'alert_type', 'period'])]
class PortfolioAlert implements TenantAwareInterface
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

    #[ORM\Column(type: 'string', length: 50)]
    private string $alertType; // 'missing_report', 'missing_kpi', 'source_conflict'

    #[ORM\Column(type: 'string', length: 20)]
    private string $period; // '2026-Q3'

    #[ORM\Column(type: 'string', length: 30)]
    private string $status = 'open'; // 'open', 'acknowledged', 'snoozed'

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $snoozeReason = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $snoozedUntil = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Tenant $tenant,
        PortfolioCompany $company,
        string $alertType,
        string $period
    )
    {
        $this->tenant = $tenant;
        $this->company = $company;
        $this->alertType = $alertType;
        $this->period = $period;
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

    public function getCompany(): PortfolioCompany
    {
        return $this->company;
    }

    public function getAlertType(): string
    {
        return $this->alertType;
    }

    public function getPeriod(): string
    {
        return $this->period;
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

    public function snooze(string $reason, \DateTimeImmutable $until): self
    {
        $this->status = 'snoozed';
        $this->snoozeReason = $reason;
        $this->snoozedUntil = $until;

        return $this;
    }

    public function getSnoozeReason(): ?string
    {
        return $this->snoozeReason;
    }

    public function getSnoozedUntil(): ?\DateTimeImmutable
    {
        return $this->snoozedUntil;
    }
}
