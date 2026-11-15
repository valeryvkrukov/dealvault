<?php

namespace DealVault\Dealflow\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'deal_stage_events')]
class StageEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Deal::class)]
    #[ORM\JoinColumn(name: 'deal_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Deal $deal;

    #[ORM\Column(type: 'string', length: 50)]
    private string $stage; // 'mapped', 'to_analyse', 'first_call', 'due_diligence', 'ic'

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $enteredAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $leftAt = null;

    public function __construct(Deal $deal, string $stage)
    {
        $this->deal = $deal;
        $this->stage = $stage;
        $this->enteredAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDeal(): Deal
    {
        return $this->deal;
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function getEnteredAt(): \DateTimeImmutable
    {
        return $this->enteredAt;
    }

    public function getLeftAt(): ?\DateTimeImmutable
    {
        return $this->leftAt;
    }

    public function closeStage(?\DateTimeImmutable $leftAt = null): self
    {
        $this->leftAt = $leftAt ?? new \DateTimeImmutable();
        return $this;
    }

    /**
     * Calculate number of full days spent on this stage
     */
    public function getDaysSpent(): int
    {
        $endDate = $this->leftAt ?? new \DateTimeImmutable();
        $interval = $this->enteredAt->diff($endDate);

        return (int) $interval->format('%a');
    }
}
