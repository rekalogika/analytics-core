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

namespace Rekalogika\Analytics\Engine\DistinctValuesResolver;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Rekalogika\Analytics\Common\Exception\MetadataException;
use Rekalogika\Analytics\Common\Exception\UnexpectedValueException;
use Rekalogika\Analytics\Contracts\DistinctValuesResolver;
use Rekalogika\Analytics\Engine\SummaryManager\Query\GetDistinctValuesFromSourceQuery;
use Rekalogika\Analytics\Metadata\Doctrine\ClassMetadataWrapper;
use Rekalogika\Analytics\Metadata\Summary\DimensionMetadata;
use Rekalogika\Analytics\Metadata\Summary\SummaryMetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final readonly class DoctrineDistinctValuesResolver implements DistinctValuesResolver
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private SummaryMetadataFactory $summaryMetadataFactory,
        private PropertyAccessorInterface $propertyAccessor,
    ) {}

    #[\Override]
    public static function getApplicableDimensions(): ?iterable
    {
        return null;
    }

    #[\Override]
    public function getDistinctValues(
        string $class,
        string $dimension,
        int $limit,
    ): null|iterable {
        // make sure the field exists

        $metadata = new ClassMetadataWrapper($this->managerRegistry, $class);

        if (!$metadata->hasProperty($dimension)) {
            throw new MetadataException(\sprintf(
                'The class "%s" does not have a field named "%s"',
                $class,
                $dimension,
            ));
        }

        // get dimension metadata

        $summaryMetadata = $this->summaryMetadataFactory
            ->getSummaryMetadata($class);

        $dimensionMetadata = $summaryMetadata->getDimension($dimension);

        // if it is a relation, we get the unique values from the source entity

        if ($metadata->isPropertyEntity($dimension)) {
            $relatedClass = $metadata->getAssociationTargetClass($dimension);

            return $this->getRelationDistinctValues(
                class: $relatedClass,
                dimensionMetadata: $dimensionMetadata,
                limit: $limit,
            );
        }

        // if enum

        $enumType = $metadata->getEnumType($dimension)
            ?? $dimensionMetadata->getTypeClass();

        if ($enumType !== null && is_a($enumType, \BackedEnum::class, true)) {
            return (function () use ($enumType) {
                foreach ($enumType::cases() as $case) {
                    yield (string) $case->value => $case;
                }
            })();
        }

        // otherwise we get the unique values from the summary table

        // return $this->getDistinctValuesFromSummary(
        //     class: $class,
        //     dimension: $dimension,
        //     dimensionMetadata: $dimensionMetadata,
        //     limit: $limit,
        // );

        return null;
    }

    #[\Override]
    public function getValueFromId(
        string $class,
        string $dimension,
        string $id,
    ): mixed {
        $metadata = new ClassMetadataWrapper($this->managerRegistry, $class);

        if (!$metadata->hasProperty($dimension)) {
            throw new MetadataException(\sprintf(
                'The class "%s" does not have a field named "%s"',
                $class,
                $dimension,
            ));
        }

        // manager

        $manager = $this->managerRegistry->getManagerForClass($class);

        if (!$manager instanceof EntityManagerInterface) {
            throw new MetadataException(\sprintf(
                'The class "%s" is not managed by Doctrine ORM',
                $class,
            ));
        }

        // get dimension metadata

        $summaryMetadata = $this->summaryMetadataFactory
            ->getSummaryMetadata($class);

        $dimensionMetadata = $summaryMetadata->getDimension($dimension);

        // if it is a relation, we get the unique values from the source entity

        if ($metadata->isPropertyEntity($dimension)) {
            $relatedClass = $metadata->getAssociationTargetClass($dimension);

            return $manager->find($relatedClass, $id);
        }

        // if enum

        if (($enumType = $metadata->getEnumType($dimension)) !== null) {
            if (is_a($enumType, \BackedEnum::class, true)) {
                try {
                    return $enumType::from($id);
                } catch (\TypeError) {
                    return $enumType::from((int) $id);
                }
            }

            throw new UnexpectedValueException(\sprintf(
                'The enum type "%s" is not a BackedEnum',
                $enumType,
            ));
        }

        // otherwise we get the unique values from the summary table

        // $values = $this->getDistinctValuesFromSummary(
        //     class: $class,
        //     dimension: $dimension,
        //     dimensionMetadata: $dimensionMetadata,
        //     limit: 100, // @todo remove hardcode
        // );

        // /** @psalm-suppress InvalidArgument */
        // $values = iterator_to_array($values);

        // return $values[$id] ?? null;

        return null;
    }


    /**
     * @param class-string $class
     * @return iterable<string,object>
     */
    private function getRelationDistinctValues(
        string $class,
        DimensionMetadata $dimensionMetadata,
        int $limit,
    ): null|iterable {
        $manager = $this->managerRegistry->getManagerForClass($class);

        if (!$manager instanceof EntityManagerInterface) {
            return null;
        }

        $query = new GetDistinctValuesFromSourceQuery(
            dimensionMetadata: $dimensionMetadata,
            entityManager: $manager,
            limit: $limit,
            propertyAccessor: $this->propertyAccessor,
        );

        return $query->getResult();
    }

    // /**
    //  * @param class-string $class Summary class name
    //  * @return iterable<string,mixed>
    //  */
    // private function getDistinctValuesFromSummary(
    //     string $class,
    //     string $dimension,
    //     DimensionMetadata $dimensionMetadata,
    //     int $limit,
    // ): iterable {
    //     $manager = $this->managerRegistry->getManagerForClass($class);

    //     if (!$manager instanceof EntityManagerInterface) {
    //         throw new \InvalidArgumentException('Invalid manager');
    //     }

    //     $queryBuilder = $manager->createQueryBuilder();

    //     $orderBy = $dimensionMetadata->getOrderBy();

    //     $queryBuilder = $manager->createQueryBuilder()
    //         ->select("DISTINCT e.$dimension AS value")
    //         ->from($class, 'e')
    //         ->orderBy("e.$dimension")
    //         ->setMaxResults($limit);

    //     $metadata = new ClassMetadataWrapper($manager->getClassMetadata($class));
    //     $idReflection = $metadata->getIdReflectionProperty();

    //     $result = $queryBuilder->getQuery()->getArrayResult();

    //     /** @var array{id:string|int,value:mixed} $row */
    //     foreach ($result as $row) {
    //         /** @var mixed */
    //         $value = $row['value'];

    //         if (\is_object($value)) {
    //             $id = $idReflection->getValue($value);
    //         } else {
    //             /** @psalm-suppress MixedAssignment */
    //             $id = $value;
    //         }

    //         if ($id instanceof \Stringable || \is_int($id)) {
    //             $id = (string) $id;
    //         } elseif ($id === null) {
    //             $id = '';
    //         }

    //         if (!\is_string($id)) {
    //             throw new \InvalidArgumentException(\sprintf(
    //                 'The ID "%s" is not a string',
    //                 get_debug_type($id),
    //             ));
    //         }

    //         yield $id => $value;
    //     }
    // }
}
