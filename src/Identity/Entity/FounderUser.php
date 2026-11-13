<?php

namespace DealVault\Identity\Entity;

use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'founder_users')]
#[ORM\UniqueConstraint(name: 'uniq_founder_email_per_tenant', columns: ['tenant_id', 'email'])]
class FounderUser implements UserInterface, PasswordAuthenticatedUserInterface, TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $portfolioCompanyId = null;

    public function __construct(
        Tenant $tenant,
        string $email
    )
    {
        $this->tenant = $tenant;
        $this->email = $email;
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

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPortfolioCompanyId(): ?string
    {
        return $this->portfolioCompanyId;
    }

    public function setPortfolioCompanyId(?string $portfolioCompanyId): self
    {
        $this->portfolioCompanyId = $portfolioCompanyId;

        return $this;
    }

    public function getRoles(): array
    {
        return ['ROLE_FOUNDER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
