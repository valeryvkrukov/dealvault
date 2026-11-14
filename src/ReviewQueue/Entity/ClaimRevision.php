<?php

namespace DealVault\ReviewQueue\Entity;

use DealVault\Ingestion\Entity\IngestionSource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_claim_revisions')]
class ClaimRevision
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: PortfolioClaim::class)]
    #[ORM\JoinColumn(name: 'claim_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PortfolioClaim $claim;

    #[ORM\ManyToOne(targetEntity: IngestionSource::class)]
    #[ORM\JoinColumn(name: 'source_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private IngestionSource $source;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sourceLocatorExcerpt = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $extractedValue;

    #[ORM\Column(type: 'string', length: 30)]
    private string $captureMethod;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $reviewedBy = null;

    #[ORM\Column(type: 'string', length: 30)]
    private string $reviewStatus = 'pending';

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $previousRevisionId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        PortfolioClaim $claim,
        IngestionSource $source,
        float $extractedValue,
        string $captureMethod
    )
    {
        $this->claim = $claim;
        $this->source = $source;
        $this->extractedValue = (string) $extractedValue;
        $this->captureMethod = $captureMethod;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getClaim(): PortfolioClaim
    {
        return $this->claim;
    }

    public function getSource(): IngestionSource
    {
        return $this->source;
    }

    public function getSourceLocatorExcerpt(): ?string
    {
        return $this->sourceLocatorExcerpt;
    }

    public function setSourceLocatorExcerpt(?string $excerpt): self
    {
        $this->sourceLocatorExcerpt = $excerpt;
        return $this;
    }

    public function getExtractedValue(): float
    {
        return (float) $this->extractedValue;
    }

    public function getReviewStatus(): string
    {
        return $this->reviewStatus;
    }

    public function setReviewStatus(string $status): self
    {
        $this->reviewStatus = $status;
        return $this;
    }

    public function getPreviousRevisionId(): ?string
    {
        return $this->previousRevisionId;
    }

    public function setPreviousRevisionId(?string $id): self
    {
        $this->previousRevisionId = $id;
        return $this;
    }
}
