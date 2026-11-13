<?php

namespace DealVault\Shared\MultiTenancy\EventListener;

use DealVault\Shared\MultiTenancy\TenantContext;
use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TenantListener implements EventSubscriberInterface
{
    private TenantContext $tenantContext;
    private TokenStorageInterface $tokenStorage;
    private EntityManagerInterface $entityManager;

    public function __construct(
        TenantContext $tenantContext,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager
    ) {
        $this->tenantContext = $tenantContext;
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 1]]
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof TenantAwareInterface) {
            return;
        }

        $tenantId = $user->getTenantId();
        if ($tenantId === null) {
            return;
        }

        $this->tenantContext->setCurrentTenantId($tenantId);

        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenantId);
        }
    }
}
