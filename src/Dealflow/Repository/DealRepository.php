<?php

namespace DealVault\Dealflow\Repository;

use DealVault\Dealflow\Entity\Deal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository for querying and managing Deal entities.
 *
 * @extends ServiceEntityRepository<Deal>
 */
class DealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deal::class);
    }

    /**
     * Makes search of deals with support for filtering by built-in and dynamic (JSONB) fields.
     * Accepts an array of filters in the form:
     * [
     *     ['field' => 'status', 'operator' => 'is', 'value' => 'due_diligence'],
     *     ['field' => 'target_ticket_size', 'operator' => 'more_than', 'value' => 500000, 'is_custom' => true]
     * ]
     */
    public function searchDeals(array $filters = [], ?string $searchQuery = null): array
    {
        $qb = $this->createQueryBuilder('d');

        // Global search by company name
        if ($searchQuery) {
            $qb->andWhere('LOWER(d.companyName) LIKE :search')
                ->setParameter('search', '%' . strtolower($searchQuery) . '%');
        }

        foreach ($filters as $index => $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            $isCustom = $filter['is_custom'] ?? false;

            $paramName = 'val_' . $index;

            if ($isCustom) {
                // Filtering by dynamic fields within JSONB (PostgreSQL 17 native syntax)
                // d.customValues ->> 'key' extracts the value as text
                $jsonField = sprintf("JSONB_GET_TEXT(d.customValues, '%s')", $field);

                switch ($operator) {
                    case 'is':
                        $qb->andWhere(sprintf("%s = :%s", $jsonField, $paramName))
                            ->setParameter($paramName, (string)$value);
                        break;
                    case 'contains':
                        $qb->andWhere(sprintf("LOWER(%s) LIKE :%s", $jsonField, $paramName))
                            ->setParameter($paramName, '%' . strtolower($value) . '%');
                        break;
                    case 'more_than':
                        // Cast to numeric field from JSON for mathematical comparison
                        $qb->andWhere(sprintf("CAST_NUMERIC(%s) > :%s", $jsonField, $paramName))
                            ->setParameter($paramName, $value);
                        break;
                    case 'less_than':
                        $qb->andWhere(sprintf("CAST_NUMERIC(%s) < :%s", $jsonField, $paramName))
                            ->setParameter($paramName, $value);
                        break;
                    case 'is_empty':
                        $qb->andWhere(sprintf("%s IS NULL OR %s = ''", $jsonField, $jsonField));
                        break;
                }
            } else {
                // Filtering by regular fields of Deal entity
                switch ($operator) {
                    case 'is':
                        $qb->andWhere(sprintf("d.%s = :%s", $field, $paramName))
                            ->setParameter($paramName, $value);
                        break;
                    case 'more_than':
                        $qb->andWhere(sprintf("d.%s > :%s", $field, $paramName))
                            ->setParameter($paramName, $value);
                        break;
                    case 'less_than':
                        $qb->andWhere(sprintf("d.%s < :%s", $field, $paramName))
                            ->setParameter($paramName, $value);
                        break;
                }
            }
        }

        return $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }
}
