<?php

namespace DealVault\Portfolio\Entity;

use AllowDynamicProperties;
use Doctrine\ORM\Mapping as ORM;

#[AllowDynamicProperties]
#[ORM\Entity]
#[ORM\Table(name: 'portfolio_reporting_calendars')]
class ReportingCalendar
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\OneToOne(targetEntity: PortfolioCompany::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PortfolioCompany $company;

    #[ORM\Column(type: 'string', length: 20)]
    private string $frequency = 'quarterly'; // 'quarterly', 'half-yearly'

    #[ORM\Column(type: 'integer')]
    private int $daysAfterPeriodEnd = 15; // by default 15 days after period end

    public function __construct(PortfolioCompany $company)
    {
        $this->company = $company;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCompany(): PortfolioCompany
    {
        return $this->company;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getDaysAfterPeriodEnd(): int
    {
        return $this->daysAfterPeriodEnd;
    }

    public function setDaysAfterPeriodEnd(int $days): self
    {
        $this->daysAfterPeriodEnd = $days;

        return $this;
    }
}
