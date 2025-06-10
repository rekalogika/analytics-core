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

namespace Rekalogika\Analytics\SummaryManager\SummarizerWorker;

use Doctrine\ORM\EntityManagerInterface;
use Rekalogika\Analytics\Contracts\Context\HierarchyContext;
use Rekalogika\Analytics\Contracts\Hierarchy\ContextAwareHierarchy;
use Rekalogika\Analytics\Exception\LogicException;
use Rekalogika\Analytics\Metadata\Summary\SummaryMetadata;
use Rekalogika\Analytics\SummaryManager\DefaultQuery;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output\DefaultMeasure;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output\DefaultMeasures;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output\DefaultRow;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output\DefaultTable;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output\DefaultTuple;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output\DefaultUnit;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class QueryResultToTableTransformer
{
    private DimensionFactory $dimensionFactory;

    private function __construct(
        private readonly DefaultQuery $query,
        private readonly SummaryMetadata $metadata,
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
        $this->dimensionFactory = new DimensionFactory();
    }

    /**
     * @param list<array<string,mixed>> $input
     */
    public static function transform(
        DefaultQuery $query,
        SummaryMetadata $metadata,
        EntityManagerInterface $entityManager,
        PropertyAccessorInterface $propertyAccessor,
        array $input,
    ): DefaultTable {
        $transformer = new self(
            query: $query,
            metadata: $metadata,
            entityManager: $entityManager,
            propertyAccessor: $propertyAccessor,
        );

        $rows = $transformer->doTransform($input);

        return new DefaultTable(
            summaryClass: $metadata->getSummaryClass(),
            rows: $rows,
        );
    }

    /**
     * @param list<array<string,mixed>> $input
     * @return list<DefaultRow>
     */
    private function doTransform(array $input): array
    {
        $rows = [];

        foreach ($input as $item) {
            $row = $this->transformOne($item);

            if ($row->isSubtotal()) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $input
     */
    public function transformOne(array $input): DefaultRow
    {
        // create the object
        $summaryClass = $this->metadata->getSummaryClass();
        $reflectionClass = new \ReflectionClass($summaryClass);
        $summaryObject = $reflectionClass->newInstanceWithoutConstructor();

        //
        // process measures
        //

        $measures = $this->query->getSelect();
        $measureValues = [];

        foreach ($measures as $name) {
            if (!\array_key_exists($name, $input)) {
                throw new LogicException(\sprintf(
                    'Measure "%s" not found',
                    $name,
                ));
            }

            /** @psalm-suppress MixedAssignment */
            $rawValue = $input[$name];

            /** @psalm-suppress MixedAssignment */
            $rawValue = $this->resolveValue(
                reflectionClass: $reflectionClass,
                propertyName: $name,
                value: $rawValue,
            );

            $this->injectValueToObject(
                object: $summaryObject,
                reflectionClass: $reflectionClass,
                propertyName: $name,
                value: $rawValue,
            );

            /** @psalm-suppress MixedAssignment */
            $value = $this->propertyAccessor->getValue($summaryObject, $name);

            $unit = $this->metadata
                ->getMeasure($name)
                ->getUnit();

            $unitSignature = $this->metadata
                ->getMeasure($name)
                ->getUnitSignature();

            $unit = DefaultUnit::create(
                label: $unit,
                signature: $unitSignature,
            );

            $measure = new DefaultMeasure(
                label: $this->getLabel($name),
                name: $name,
                value: $value,
                rawValue: $rawValue,
                unit: $unit,
            );

            $measureValues[$name] = $measure;
        }

        //
        // process grouping
        //

        /** @psalm-suppress MixedAssignment */
        $groupings = $input['__grouping']
            ?? throw new LogicException('Grouping not found');

        if (!\is_string($groupings)) {
            throw new LogicException('Grouping is not a string');
        }

        //
        // process dimensions
        //

        $dimensionValues = [];
        $tuple = $this->query->getGroupBy();

        foreach ($tuple as $name) {
            if ($name === '@values') {
                continue;
            }

            if (!\array_key_exists($name, $input)) {
                throw new LogicException(\sprintf('Dimension "%s" not found', $name));
            }

            /** @psalm-suppress MixedAssignment */
            $rawValue = $input[$name];

            /** @psalm-suppress MixedAssignment */
            $rawValue = $this->resolveValue(
                reflectionClass: $reflectionClass,
                propertyName: $name,
                value: $rawValue,
            );

            $this->injectValueToObject(
                object: $summaryObject,
                reflectionClass: $reflectionClass,
                propertyName: $name,
                value: $rawValue,
            );

            /** @psalm-suppress MixedAssignment */
            $value = $this->propertyAccessor->getValue($summaryObject, $name);

            /** @psalm-suppress MixedAssignment */
            $displayValue = $value ?? $this->getNullValue($name);

            $dimension = $this->dimensionFactory->createDimension(
                label: $this->getLabel($name),
                name: $name,
                member: $value,
                rawMember: $rawValue,
                displayMember: $displayValue,
            );

            $dimensionValues[$name] = $dimension;
        }

        //
        // instantiate
        //

        $tuple = new DefaultTuple(
            summaryClass: $this->metadata->getSummaryClass(),
            dimensions: $dimensionValues,
        );

        $measures = new DefaultMeasures($measureValues);

        return new DefaultRow(
            tuple: $tuple,
            measures: $measures,
            groupings: $groupings,
        );
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function resolveValue(
        \ReflectionClass $reflectionClass,
        string $propertyName,
        mixed $value,
    ): mixed {
        if (str_contains($propertyName, '.')) {
            return $value;
            // [$propertyName, $hierarchyPropertyName] = explode('.', $key);

            // $reflectionProperty = $reflectionClass->getProperty($propertyName);
            // $reflectionType = $reflectionProperty->getType();

            // if ($reflectionType instanceof \ReflectionNamedType) {
            //     $propertyClass = $reflectionType->getName();

            //     if (!class_exists($propertyClass)) {
            //         return $value;
            //     }
            // } else {
            //     return $value;
            // }
        }

        $reflectionProperty = $this->getReflectionProperty($reflectionClass, $propertyName);
        $propertyClass = $this->getTypeOfProperty($reflectionProperty);

        if ($value === null || \is_object($value)) {
            return $value;
        }

        if ($propertyClass === null) {
            return $value;
        }

        // for older Doctrine version that don't correctly hydrate
        // enums with QueryBuilder
        if (is_a($propertyClass, \BackedEnum::class, true) && (\is_int($value) || \is_string($value))) {
            return $propertyClass::from($value);
        }

        // determine if propertyClass is an entity
        $isEntity = !$this->entityManager
            ->getMetadataFactory()
            ->isTransient($propertyClass);

        if ($isEntity) {
            return $this->entityManager
                ->getReference($propertyClass, $value);
        }

        return $value;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function getReflectionProperty(
        \ReflectionClass $reflectionClass,
        string $propertyName,
    ): \ReflectionProperty {
        if ($reflectionClass->hasProperty($propertyName)) {
            return $reflectionClass->getProperty($propertyName);
        }

        $parent = $reflectionClass->getParentClass();

        if ($parent === false) {
            throw new LogicException(\sprintf('Property "%s" not found in class "%s"', $propertyName, $reflectionClass->getName()));
        }

        return $this->getReflectionProperty($parent, $propertyName);
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function injectValueToObject(
        object $object,
        \ReflectionClass $reflectionClass,
        string $propertyName,
        mixed $value,
    ): void {
        if (str_contains($propertyName, '.')) {
            [$propertyName, $hierarchyPropertyName] = explode('.', $propertyName);

            $this->injectValueToHierarchyObject(
                object: $object,
                reflectionClass: $reflectionClass,
                propertyName: $propertyName,
                hierarchyPropertyName: $hierarchyPropertyName,
                value: $value,
            );

            return;
        }

        while (true) {
            if ($reflectionClass->hasProperty($propertyName)) {
                $property = $reflectionClass->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($object, $value);

                return;
            }

            $reflectionClass = $reflectionClass->getParentClass();

            if ($reflectionClass === false) {
                throw new LogicException('Property not found');
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function injectValueToHierarchyObject(
        object $object,
        \ReflectionClass $reflectionClass,
        string $propertyName,
        string $hierarchyPropertyName,
        mixed $value,
    ): void {
        // initialize hierarchy object

        $curReflectionClass = $reflectionClass;
        $hierarchyObjectReflection = null;
        $hierarchyObject = null;

        while (true) {
            if ($curReflectionClass->hasProperty($propertyName)) {
                $reflectionProperty = $curReflectionClass->getProperty($propertyName);
                $reflectionProperty->setAccessible(true);

                if (!$reflectionProperty->isInitialized($object)) {
                    $reflectionType = $reflectionProperty->getType();

                    if (!$reflectionType instanceof \ReflectionNamedType) {
                        throw new LogicException('Property type not found');
                    }

                    $propertyClass = $reflectionType->getName();

                    if (!class_exists($propertyClass)) {
                        throw new LogicException('Property class not found');
                    }

                    $hierarchyClassReflection = new \ReflectionClass($propertyClass);
                    $hierarchyObject = $hierarchyClassReflection->newInstanceWithoutConstructor();

                    $reflectionProperty->setValue($object, $hierarchyObject);
                }

                /** @var mixed */
                $hierarchyObject = $reflectionProperty->getValue($object);

                if (!\is_object($hierarchyObject)) {
                    throw new LogicException('Hierarchy object not found');
                }

                $hierarchyObjectReflection = new \ReflectionObject($hierarchyObject);

                break;
            }

            $curReflectionClass = $curReflectionClass->getParentClass();

            if ($curReflectionClass === false) {
                throw new LogicException('Property not found');
            }
        }

        // inject time zone if applicable
        // @todo optimize

        if ($hierarchyObject instanceof ContextAwareHierarchy) {
            $hierarchyContext = new HierarchyContext(
                summaryMetadata: $this->metadata,
                dimensionMetadata: $this->metadata
                    ->getDimension($propertyName),
                dimensionHierarchyMetadata: $this->metadata
                    ->getDimension($propertyName)
                    ->getHierarchy() ?? throw new LogicException(
                        \sprintf('Dimension "%s" does not have a hierarchy', $propertyName),
                    ),
            );

            $hierarchyObject->setContext($hierarchyContext);
        }

        // inject value to hierarchy object

        if ($hierarchyObjectReflection === null) {
            throw new LogicException('Hierarchy object not found');
        }

        while (true) {
            if ($hierarchyObjectReflection->hasProperty($hierarchyPropertyName)) {
                $property = $hierarchyObjectReflection->getProperty($hierarchyPropertyName);
                $property->setAccessible(true);
                $property->setValue($hierarchyObject, $value);

                return;
            }

            $hierarchyObjectReflection = $hierarchyObjectReflection->getParentClass();

            if ($hierarchyObjectReflection === false) {
                throw new LogicException('Property not found');
            }
        }
    }

    /**
     * @return class-string|null
     */
    private function getTypeOfProperty(\ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        $class = $type->getName();

        if (!class_exists($class)) {
            return null;
        }

        return $class;
    }

    private function getLabel(string $property): TranslatableInterface
    {
        return $this->metadata
            ->getProperty($property)
            ->getLabel();
    }

    private function getNullValue(string $dimension): TranslatableInterface
    {
        return $this->metadata
            ->getDimensionOrDimensionProperty($dimension)
            ->getNullLabel();
    }
}
