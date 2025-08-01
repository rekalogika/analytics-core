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

namespace Rekalogika\Analytics\Engine\DistinctValuesResolver\Query;

use Doctrine\ORM\EntityManagerInterface;
use Rekalogika\Analytics\Contracts\Exception\MetadataException;
use Rekalogika\Analytics\Contracts\Exception\UnexpectedValueException;
use Rekalogika\Analytics\Engine\Infrastructure\AbstractQuery;
use Rekalogika\Analytics\Metadata\Summary\DimensionMetadata;
use Rekalogika\Analytics\SimpleQueryBuilder\SimpleQueryBuilder;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @todo restore functionality to resolve order by
 */
final class GetDistinctValuesFromSourceQuery extends AbstractQuery
{
    public function __construct(
        DimensionMetadata $dimensionMetadata,
        EntityManagerInterface $entityManager,
        private readonly PropertyAccessorInterface $propertyAccessor,
        int $limit,
    ) {
        $summaryMetadata = $dimensionMetadata->getSummaryMetadata();
        $summaryClass = $summaryMetadata->getSummaryClass();
        // $orderBy = $dimensionMetadata->getOrderBy();
        $dimension = $dimensionMetadata->getName();

        $doctrineMetadata = $entityManager->getClassMetadata($summaryClass);

        // ensure the property is a relation
        if (!$doctrineMetadata->hasAssociation($dimension)) {
            throw new MetadataException(\sprintf(
                'The property "%s" in class "%s" is not a relation',
                $dimension,
                $summaryClass,
            ));
        }

        // get relation class
        $relationClass = $doctrineMetadata->getAssociationTargetClass($dimension);

        $simpleQueryBuilder = new SimpleQueryBuilder(
            entityManager: $entityManager,
            from: $relationClass,
            alias: 'root',
        );

        $simpleQueryBuilder
            ->select('root')
            ->setMaxResults($limit);

        parent::__construct($simpleQueryBuilder);

        // if (\is_array($orderBy)) {
        //     foreach ($orderBy as $field => $direction) {
        //         $fieldExpression = $this->resolve($field);

        //         $simpleQueryBuilder->addOrderBy($fieldExpression, $direction->value);
        //     }
        // }
    }

    /**
     * @return iterable<string,object>
     */
    public function getResult(): iterable
    {
        /** @var list<object> */
        $result = $this->getSimpleQueryBuilder()->getQuery()->getResult();

        $idField = $this->getSimpleQueryBuilder()->getEntityManager()
            ->getClassMetadata($this->getSimpleQueryBuilder()->getRootEntities()[0])
            ->getSingleIdentifierFieldName();

        foreach ($result as $item) {
            $id = $this->propertyAccessor->getValue($item, $idField);

            if (!\is_string($id) && !\is_int($id)) {
                throw new UnexpectedValueException(\sprintf(
                    'The identifier field "%s" in class "%s" is not a string or integer',
                    $idField,
                    $this->getSimpleQueryBuilder()->getRootEntities()[0],
                ));
            }

            $id = (string) $id;

            yield $id => $item;
        }
    }
}
