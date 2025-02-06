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

namespace Rekalogika\Analytics\SummaryManager\SummarizerWorker\Model;

use Rekalogika\Analytics\Query\ResultNode;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * @implements \IteratorAggregate<mixed,ResultNode>
 */
final class DefaultSummaryNode implements ResultNode, \IteratorAggregate
{
    use NodeTrait;

    /**
     * @var list<DefaultSummaryNode>
     */
    private array $children = [];

    private ?DefaultSummaryNode $parent = null;

    private function __construct(
        private readonly string $key,
        private readonly object|int|float|null $value,
        private readonly int|float|null $rawValue,
        private readonly string|TranslatableInterface $legend,
        private readonly mixed $item,
        private readonly bool $leaf,
    ) {}

    public function count(): int
    {
        return \count($this->children);
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->children as $child) {
            yield $child->getItem() => $child;
        }
    }

    public static function createBranchItem(
        string $key,
        string|TranslatableInterface $legend,
        mixed $item,
    ): self {
        return new self(
            key: $key,
            legend: $legend,
            item: $item,
            value: null,
            rawValue: null,
            leaf: false,
        );
    }

    public static function createLeafItem(
        string $key,
        object|int|float|null $value,
        int|float|null $rawValue,
        string|TranslatableInterface $legend,
        mixed $item,
    ): self {
        return new self(
            key: $key,
            legend: $legend,
            item: $item,
            value: $value,
            rawValue: $rawValue,
            leaf: true,
        );
    }

    public function isEqual(self $other): bool
    {
        return $this->key === $other->key
            && $this->item === $other->item;
        ;
    }

    public function isLeaf(): bool
    {
        return $this->leaf;
    }

    public function getLegend(): string|TranslatableInterface
    {
        return $this->legend;
    }

    public function getItem(): mixed
    {
        return $this->item;
    }

    public function setParent(DefaultSummaryNode $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): ?DefaultSummaryNode
    {
        return $this->parent;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function __clone()
    {
        $this->children = [];
    }

    public function addChild(DefaultSummaryNode $item): void
    {
        $this->children[] = $item;
        $item->setParent($this);
    }

    public function getValue(): object|int|float|null
    {
        return $this->value;
    }

    public function getRawValue(): int|float|null
    {
        return $this->rawValue;
    }

    public function getMeasurePropertyName(): ?string
    {
        if ($this->item instanceof MeasureDescription) {
            return $this->item->getMeasurePropertyName();
        }

        return null;
    }
}
