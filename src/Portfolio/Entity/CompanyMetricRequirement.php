<?php

namespace DealVault\Portfolio\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_metric_requirements')]
class CompanyMetricRequirement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: PortfolioCompany::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PortfolioCompany $company;

    #[ORM\Column(type: 'string', length: 100)]
    private string $metricName; // for ex.: 'arr', 'revenue', 'headcount'

    #[ORM\Column(type: 'boolean')]
    private bool $isRequired = true;

    public function __construct(
        PortfolioCompany $company,
        string $metricName
    )
    {
        $this->company = $company;
        $this->metricName = $metricName;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCompany(): PortfolioCompany
    {
        return $this->company;
    }

    public function getMetricName(): string
    {
        return $this->metricName;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): self
    {
        $this->isRequired = $isRequired;

        return $this;
    }
}
