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

namespace Rekalogika\Analytics\Engine\SummaryQuery\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Rekalogika\Analytics\Contracts\Context\SummaryContext;
use Rekalogika\Analytics\Contracts\Exception\LogicException;
use Rekalogika\Analytics\Contracts\Summary\ContextAwareSummary;
use Rekalogika\Analytics\Engine\SummaryQuery\DefaultQuery;
use Rekalogika\Analytics\Engine\SummaryQuery\Helper\GroupingField;
use Rekalogika\Analytics\Engine\SummaryQuery\Helper\QueryResultToTableHelper;
use Rekalogika\Analytics\Engine\SummaryQuery\Helper\ResultContext;
use Rekalogika\Analytics\Engine\SummaryQuery\Output\DefaultDimension;
use Rekalogika\Analytics\Engine\SummaryQuery\Output\DefaultMeasure;
use Rekalogika\Analytics\Engine\SummaryQuery\Output\DefaultMeasures;
use Rekalogika\Analytics\Engine\SummaryQuery\Output\DefaultRow;
use Rekalogika\Analytics\Engine\SummaryQuery\Output\DefaultTable;
use Rekalogika\Analytics\Engine\SummaryQuery\Output\DefaultTuple;
use Rekalogika\Analytics\Engine\SummaryQuery\Output\DefaultUnit;
use Rekalogika\Analytics\Metadata\Summary\DimensionMetadata;
use Rekalogika\Analytics\Metadata\Summary\SummaryMetadata;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class QueryResultToTableTransformer
{
    private QueryResultToTableHelper $helper;

    /**
     * @var list<string>
     */
    private array $dimensions;

    private function __construct(
        private readonly DefaultQuery $query,
        private readonly SummaryMetadata $metadata,
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ResultContext $context,
    ) {
        $this->helper = new QueryResultToTableHelper();

        $this->dimensions = array_values(array_filter(
            $this->query->getGroupBy(),
            static fn(string $dimension): bool => $dimension !== '@values',
        ));
    }

    /**
     * @param list<array<string,mixed>> $input
     */
    public static function transform(
        DefaultQuery $query,
        SummaryMetadata $metadata,
        EntityManagerInterface $entityManager,
        PropertyAccessorInterface $propertyAccessor,
        ResultContext $context,
        array $input,
    ): DefaultTable {
        $transformer = new self(
            query: $query,
            metadata: $metadata,
            entityManager: $entityManager,
            propertyAccessor: $propertyAccessor,
            context: $context,
        );

        return new DefaultTable(
            summaryClass: $metadata->getSummaryClass(),
            rows: $transformer->doTransform($input),
            context: $context,
        );
    }

    /**
     * @param list<array<string,mixed>> $input
     * @return iterable<int,DefaultRow>
     */
    private function doTransform(array $input): iterable
    {
        foreach ($input as $item) {
            yield $this->transformOne($item);
        }
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

        // create groupings
        $groupings = new GroupingField(
            groupingField: $input['__grouping'] ?? null,
            dimensions: $this->dimensions,
        );

        // contextawareness init
        if ($summaryObject instanceof ContextAwareSummary) {
            $summaryObject->setContext(
                context: new SummaryContext(
                    summaryMetadata: $this->metadata,
                    rawInput: $input,
                ),
            );
        }

        // populate dimensions
        $this->populateDimensions(
            input: $input,
            summaryObject: $summaryObject,
        );

        // populate measures
        $this->populateMeasures(
            input: $input,
            summaryObject: $summaryObject,
        );

        // get dimensions from object
        $dimensionValues = $this->createDimensionValues($summaryObject, $groupings);

        // get measures from object
        $measureValues = $this->createMeasureValues($summaryObject);

        // instantiate
        $tuple = new DefaultTuple(
            summaryClass: $this->metadata->getSummaryClass(),
            dimensions: array_values($dimensionValues),
            condition: $this->query->getWhere(),
        );

        $measures = new DefaultMeasures($measureValues);

        return new DefaultRow(
            tuple: $tuple,
            measures: $measures,
            groupings: $groupings,
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    private function populateDimensions(
        array $input,
        object $summaryObject,
    ): void {
        foreach ($this->dimensions as $name) {
            if (!\array_key_exists($name, $input)) {
                throw new LogicException(\sprintf('Dimension "%s" not found', $name));
            }

            /** @psalm-suppress MixedAssignment */
            $rawValue = $this->normalizeRawValue(
                propertyName: $name,
                value: $input[$name],
            );

            $this->helper->setValue(
                object: $summaryObject,
                property: $name,
                value: $rawValue,
                metadata: $this->metadata,
            );
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    private function populateMeasures(
        array $input,
        object $summaryObject,
    ): void {
        $measures = $this->query->getSelect();

        foreach ($measures as $name) {
            if (!\array_key_exists($name, $input)) {
                throw new LogicException(\sprintf('Measure "%s" not found', $name));
            }

            /** @psalm-suppress MixedAssignment */
            $rawValue = $this->normalizeRawValue(
                propertyName: $name,
                value: $input[$name],
            );

            $this->helper->setValue(
                object: $summaryObject,
                property: $name,
                value: $rawValue,
                metadata: $this->metadata,
            );
        }
    }

    /**
     * @return array<string,DefaultDimension>
     */
    private function createDimensionValues(
        object $summaryObject,
        GroupingField $groupingField,
    ): array {
        $dimensions = $groupingField->getNonGroupingFields();
        $dimensionValues = [];

        foreach ($dimensions as $name) {
            /** @psalm-suppress MixedAssignment */
            $rawValue = $this->helper->getValue(
                object: $summaryObject,
                property: $name,
                metadata: $this->metadata,
            );

            /** @psalm-suppress MixedAssignment */
            $value = $this->propertyAccessor->getValue($summaryObject, $name);

            /** @psalm-suppress MixedAssignment */
            $displayValue = $value ?? $this->getNullValue($name);

            $dimension = $this->context->getDimensionFactory()->createDimension(
                label: $this->getLabel($name),
                name: $name,
                member: $value,
                rawMember: $rawValue,
                displayMember: $displayValue,
                interpolation: false,
            );

            $dimensionValues[$name] = $dimension;
        }

        return $dimensionValues;
    }

    /**
     * @return array<string,DefaultMeasure>
     */
    private function createMeasureValues(
        object $summaryObject,
    ): array {
        $measures = $this->query->getSelect();
        $measureValues = [];

        foreach ($measures as $name) {
            /** @psalm-suppress MixedAssignment */
            $rawValue = $this->helper->getValue(
                object: $summaryObject,
                property: $name,
                metadata: $this->metadata,
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

            $this->context->getNullMeasureCollection()->collectMeasure($measure);
        }

        return $measureValues;
    }

    private function normalizeRawValue(
        string $propertyName,
        mixed $value,
    ): mixed {
        $propertyClass = $this->metadata
            ->getProperty($propertyName)
            ->getTypeClass();

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

    private function getLabel(string $property): TranslatableInterface
    {
        $property = $this->metadata->getProperty($property);

        if ($property instanceof DimensionMetadata) {
            return $property->getLabel()->getRootToLeaf();
        }

        return $property->getLabel();
    }

    private function getNullValue(string $dimension): TranslatableInterface
    {
        return $this->metadata
            ->getDimension($dimension)
            ->getNullLabel();
    }
}
