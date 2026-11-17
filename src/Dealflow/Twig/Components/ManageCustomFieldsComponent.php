<?php

namespace DealVault\Dealflow\Twig\Components;

use DealVault\Dealflow\Entity\CustomFieldDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('manage_custom_fields')]
class ManageCustomFieldsComponent
{
    use DefaultActionTrait;

    private EntityManagerInterface $entityManager;
    private Security $security;

    #[LiveProp(writable: true)]
    public string $fieldKey = '';

    #[LiveProp(writable: true)]
    public string $fieldName = '';

    #[LiveProp(writable: true)]
    public string $fieldType = 'text'; // 'text', 'number', 'date', 'yes_no'

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security
    )
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function getDefinitions(): array
    {
        return $this->entityManager->getRepository(CustomFieldDefinition::class)->findBy(
            ['isRetired' => false],
            ['fieldName' => 'ASC']
        );
    }

    #[LiveAction]
    public function saveDefinition(): void
    {
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'getTenant') || empty($this->fieldKey) || empty($this->fieldName)) {
            return;
        }

        $cleanKey = preg_replace('/[^a-z0-9_]/', '', strtolower($this->fieldKey));
        $definition = new CustomFieldDefinition(
            $user->getTenant(),
            $cleanKey,
            $this->fieldName,
            $this->fieldType
        );

        $this->entityManager->persist($definition);
        $this->entityManager->flush();

        $this->fieldKey = '';
        $this->fieldName = '';
    }

    #[LiveAction]
    public function retireField(#[LiveArg] string $id): void
    {
        $definition = $this->entityManager->getRepository(CustomFieldDefinition::class)->find($id);
        if ($definition) {
            // Retiring a field keeps its historical values but hides from setup
            $definition->setRetired(true);
            $this->entityManager->flush();
        }
    }
}
