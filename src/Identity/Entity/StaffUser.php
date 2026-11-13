<?php

namespace DealVault\Identity\Entity;

use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'staff_users')]
#[ORM\UniqueConstraint(name: 'uniq_staff_email_per_tenant', columns: ['tenant_id', 'email'])]
class StaffUser implements UserInterface, PasswordAuthenticatedUserInterface, TenantAwareInterface
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

    #[ORM\Column(type: 'string', length: 150)]
    private string $fullName;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'string', length: 50)]
    private string $role;

    public function __construct(
        Tenant $tenant,
        string $email,
        string $fullName,
        string $role
    )
    {
        $this->tenant = $tenant;
        $this->email = $email;
        $this->fullName = $fullName;
        $this->role = $role;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;

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

    public function getRoles(): array
    {
        return [$this->role];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
