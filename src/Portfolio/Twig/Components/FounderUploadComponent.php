<?php

namespace DealVault\Portfolio\Twig\Components;

use DealVault\Ingestion\Entity\IngestionSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('founder_upload')]
class FounderUploadComponent
{
    use DefaultActionTrait;

    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function getUploadHistory(): array
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }

        return $this->entityManager->getRepository(IngestionSource::class)->findBy(
            ['tenant' => $user->getTenant()->getId()],
            ['createdAt' => 'DESC'],
            10
        );
    }
}
