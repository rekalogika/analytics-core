<?php

declare(strict_types=1);

/*
 * This file is part of rekalogika/analytics package.
 *
 * (c) Priyadi Iman Nurcahyo <https://rekalogika.dev>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Rekalogika\Analytics\SummaryManager\Query;

use Doctrine\ORM\QueryBuilder;
use Rekalogika\Analytics\Metadata\DimensionMetadata;

final class GetDistinctValuesFromSourceQuery extends AbstractQuery
{
    public function __construct(
        DimensionMetadata $dimensionMetadata,
        private readonly QueryBuilder $queryBuilder,
        int $limit,
    ) {
        $summaryMetadata = $dimensionMetadata->getSummaryMetadata();
        $summaryClass = $summaryMetadata->getSummaryClass();
        $orderBy = $dimensionMetadata->getOrderBy();
        $dimension = $dimensionMetadata->getSummaryProperty();

        $entityManager = $this->queryBuilder->getEntityManager();
        $doctrineMetadata = $entityManager->getClassMetadata($summaryClass);

        // ensure the property is a relation
        if (!$doctrineMetadata->hasAssociation($dimension)) {
            throw new \InvalidArgumentException(\sprintf(
                'The property "%s" in class "%s" is not a relation',
                $dimension,
                $summaryClass,
            ));
        }

        // get relation class
        $relationClass = $doctrineMetadata->getAssociationTargetClass($dimension);

        $queryBuilder
            ->from($relationClass, 'root')
            ->select('root')
            ->setMaxResults($limit);

        parent::__construct($queryBuilder);

        if (\is_array($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $dqlField = $this->getQueryContext()->resolvePath($field);

                $queryBuilder->addOrderBy($dqlField, $direction->value);
            }
        }
    }

    /**
     * @return list<mixed>
     */
    public function getResult(): array
    {
        return array_values($this->queryBuilder->getQuery()->getArrayResult());
    }
}
