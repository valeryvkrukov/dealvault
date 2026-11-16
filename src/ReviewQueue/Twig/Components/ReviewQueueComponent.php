<?php

namespace DealVault\ReviewQueue\Twig\Components;

use DealVault\ReviewQueue\Entity\PortfolioClaim;
use DealVault\ReviewQueue\Entity\ClaimRevision;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('review_queue')]
class ReviewQueueComponent
{
    use DefaultActionTrait;

    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security
    )
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function getPendingClaims(): array
    {
        // Select claims with statuses 'staged' or 'conflicting'
        return $this->entityManager->getRepository(PortfolioClaim::class)->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->setParameter('statuses', ['staged', 'conflicting'])
            ->orderBy('c.period', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get revisions for a given claim
     *
     * @param PortfolioClaim $claim
     * @return array
     */
    public function getRevisionsForClaim(PortfolioClaim $claim): array
    {
        return $this->entityManager->getRepository(ClaimRevision::class)->findBy(
            ['claim' => $claim->getId()],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * @throws \ReflectionException
     */
    #[LiveAction]
    public function acceptRevision(
        #[LiveArg] string $claimId,
        #[LiveArg] string $revisionId
    ): void
    {
        /** @var PortfolioClaim|null $claim */
        $claim = $this->entityManager->getRepository(PortfolioClaim::class)->find($claimId);
        /** @var ClaimRevision|null $revision */
        $revision = $this->entityManager->getRepository(ClaimRevision::class)->find($revisionId);

        if ($claim && $revision) {
            $userEmail = $this->security->getUser() ? $this->security->getUser()->getUserIdentifier() : 'analyst@horizon.com';

            // Mark revision as accepted by human
            $revision->setReviewStatus('accepted');

            // Fix reviewer email via Reflection
            $reflection = new \ReflectionClass($revision);
            $property = $reflection->getProperty('reviewedBy');
            $property->setValue($revision, $userEmail);

            // Mark claim as validated
            $claim->setStatus('validated');
            $claim->setActiveRevisionId($revision->getId());

            $this->entityManager->flush();
        }
    }

    #[LiveAction]
    public function rejectClaim(#[LiveArg] string $claimId): void
    {
        /** @var PortfolioClaim|null $claim */
        $claim = $this->entityManager->getRepository(PortfolioClaim::class)->find($claimId);

        if ($claim) {
            // Instead of hard physical deletion, change status to rejected
            $claim->setStatus('rejected');
            $this->entityManager->flush();
        }
    }
}
