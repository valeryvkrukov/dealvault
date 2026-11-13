<?php

namespace DealVault\Shared\MultiTenancy\Doctrine;

use DealVault\Shared\MultiTenancy\TenantAwareInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use ReflectionException;

class TenantFilter extends SQLFilter
{
    /**
     * @throws ReflectionException
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->reflClass->implementsInterface(TenantAwareInterface::class))
        {
            return '';
        }

        return sprintf('%s.tenant_id = %s', $targetTableAlias, $this->getParameter('tenant_id'));
    }
}
