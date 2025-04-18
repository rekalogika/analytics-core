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

namespace Rekalogika\Analytics\SummaryManager\SummarizerWorker\ItemCollector;

use Rekalogika\Analytics\Exception\InvalidArgumentException;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output\DefaultMeasure;

/**
 * @implements \IteratorAggregate<DimensionCollection>
 */
final readonly class Items implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string,DimensionCollection> $dimensions
     * @param array<string,DefaultMeasure> $measures
     */
    public function __construct(
        private array $dimensions,
        private array $measures,
    ) {}

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->dimensions;
    }

    public function getDimensions(string $key): DimensionCollection
    {
        return $this->dimensions[$key]
            ?? throw new InvalidArgumentException(\sprintf(
                'Dimension "%s" not found',
                $key,
            ));
    }

    public function getMeasure(string $key): DefaultMeasure
    {
        return $this->measures[$key]
            ?? throw new InvalidArgumentException(\sprintf(
                'Measure "%s" not found',
                $key,
            ));
    }

    public function getKeyAfter(?string $key): ?string
    {
        $keys = array_keys($this->dimensions);

        if ($key === null) {
            return $keys[0] ?? null;
        }

        $keyIndex = array_search($key, $keys, true);

        if ($keyIndex === false) {
            return null;
        }

        $nextKeyIndex = $keyIndex + 1;

        if (!isset($keys[$nextKeyIndex])) {
            return null;
        }

        return $keys[$nextKeyIndex];
    }


    #[\Override]
    public function count(): int
    {
        return \count($this->dimensions);
    }
}
