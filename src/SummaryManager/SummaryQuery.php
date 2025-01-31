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

namespace Rekalogika\Analytics\SummaryManager;

use Doctrine\ORM\EntityManagerInterface;
use Rekalogika\Analytics\Metadata\SummaryMetadata;
use Rekalogika\Analytics\Query\SummaryResult;
use Rekalogika\Analytics\SummaryManager\Filter\Filter;
use Rekalogika\Analytics\SummaryManager\Query\SummarizerQuery;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

final class SummaryQuery
{
    private mixed $lowerBound = null;

    private mixed $upperBound = null;

    /**
     * @var list<string>
     */
    private array $dimensions = [];

    /**
     * @var list<string>
     */
    private array $measures = [];

    /**
     * @var array<string,Filter>
     */
    private array $filters = [];

    /**
     * @param non-empty-array<string,Item> $dimensionChoices
     * @param array<string,Item> $measureChoices
     * @param non-empty-array<string,string|TranslatableInterface|\Stringable|iterable<string,TranslatableInterface>> $hierarchicalDimensionChoices
     */
    public function __construct(
        private readonly array $dimensionChoices,
        private readonly array $hierarchicalDimensionChoices,
        private readonly array $measureChoices,
        private readonly EntityManagerInterface $entityManager,
        private readonly SummaryMetadata $metadata,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {}

    public function getResult(): SummaryResult
    {
        $summarizer = new SummarizerQuery(
            queryBuilder: $this->entityManager->createQueryBuilder(),
            query: $this,
            metadata: $this->metadata,
            propertyAccessor: $this->propertyAccessor,
        );

        return $summarizer->execute();
    }

    //
    // available choices
    //

    /**
     * @return array<string,Item>
     */
    public function getDimensionChoices(): array
    {
        return $this->dimensionChoices;
    }

    /**
     * @return array<string,string|TranslatableInterface|\Stringable|iterable<string,string|TranslatableInterface>>
     * @todo deprecate
     * @internal
     */
    public function getHierarchicalDimensionChoices(): array
    {
        return $this->hierarchicalDimensionChoices;
    }

    /**
     * @return array<string,Item>
     */
    public function getMeasureChoices(): array
    {
        return $this->measureChoices;
    }

    //
    // bounds
    //

    public function getLowerBound(): mixed
    {
        return $this->lowerBound;
    }

    public function setLowerBound(mixed $lowerBound): void
    {
        $this->lowerBound = $lowerBound;
    }

    public function getUpperBound(): mixed
    {
        return $this->upperBound;
    }

    public function setUpperBound(mixed $upperBound): void
    {
        $this->upperBound = $upperBound;
    }

    //
    // helpers
    //

    /**
     * @param list<string> $items
     * @param 'dimension'|'measure' $type
     */
    private function ensureItemsValid(array $items, string $type): void
    {
        $invalid = [];
        $type = match ($type) {
            'dimension' => $this->dimensionChoices,
            'measure' => $this->measureChoices,
        };

        foreach ($items as $item) {
            if (!\array_key_exists($item, $type)) {
                $invalid[] = $item;
            }
        }

        if ($invalid !== []) {
            throw new \InvalidArgumentException(\sprintf('Invalid dimensions: %s', implode(', ', $invalid)));
        }
    }

    //
    // dimensions
    //

    /**
     * @return list<string>
     */
    public function getGroupBy(): array
    {
        return $this->dimensions;
    }

    public function groupBy(string ...$dimensions): void
    {
        $dimensions = array_values(array_unique($dimensions));
        $this->ensureItemsValid($dimensions, 'dimension');

        $this->dimensions = $dimensions;
    }

    public function addGroupBy(string ...$dimensions): void
    {
        $this->groupBy(...array_merge($this->dimensions, $dimensions));
    }

    //
    // measures
    //

    /**
     * @return list<string>
     */
    public function getSelect(): array
    {
        return $this->measures;
    }

    public function select(string ...$measures): void
    {
        $measures = array_values(array_unique($measures));
        $this->ensureItemsValid($measures, 'measure');

        $this->measures = $measures;
    }

    public function addSelect(string ...$measures): void
    {
        $this->select(...array_merge($this->measures, $measures));
    }

    //
    // filters
    //

    /**
     * @return array<string,Filter>
     */
    public function getWhere(): array
    {
        return $this->filters;
    }

    public function where(string $dimension, Filter $filter): void
    {
        $this->filters = [];

        $this->andWhere($dimension, $filter);
    }

    public function andWhere(string $dimension, ?Filter $filter): void
    {
        if ($filter === null) {
            unset($this->filters[$dimension]);
        } elseif (isset($this->filters[$dimension])) {
            throw new \InvalidArgumentException(\sprintf('Filter for dimension "%s" already exists', $dimension));
        } else {
            $this->filters[$dimension] = $filter;
        }
    }
}
