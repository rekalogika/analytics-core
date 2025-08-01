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

namespace Rekalogika\Analytics\Metadata\Summary;

use Rekalogika\Analytics\Contracts\Model\Partition;
use Rekalogika\Analytics\Contracts\Summary\PartitionValueResolver;
use Rekalogika\Analytics\Contracts\Translation\LiteralString;
use Rekalogika\Analytics\Metadata\AttributeCollection\AttributeCollection;

/**
 * @todo improve naming
 */
final readonly class PartitionMetadata extends PropertyMetadata
{
    /**
     * @param PartitionValueResolver<mixed> $source
     * @param class-string<Partition> $partitionClass
     */
    public function __construct(
        private PartitionValueResolver $source,
        string $summaryProperty,
        private string $partitionClass,
        private string $partitionLevelProperty,
        private string $partitionKeyProperty,
        AttributeCollection $attributes,
        ?SummaryMetadata $summaryMetadata = null,
    ) {
        parent::__construct(
            name: $summaryProperty,
            propertyName: $summaryProperty,
            label: new LiteralString('Partition'),
            typeClass: $partitionClass,
            summaryMetadata: $summaryMetadata,
            attributes: $attributes,
            involvedSourceProperties: $source->getInvolvedProperties(),
            hidden: true,
        );
    }

    public function withSummaryMetadata(SummaryMetadata $summaryMetadata): self
    {
        return new self(
            source: $this->source,
            summaryProperty: $this->getName(),
            partitionClass: $this->partitionClass,
            partitionLevelProperty: $this->partitionLevelProperty,
            partitionKeyProperty: $this->partitionKeyProperty,
            attributes: $this->getAttributes(),
            summaryMetadata: $summaryMetadata,
        );
    }

    /**
     * @return PartitionValueResolver<mixed>
     */
    public function getSource(): PartitionValueResolver
    {
        return $this->source;
    }

    /**
     * @return class-string<Partition>
     */
    public function getPartitionClass(): string
    {
        return $this->partitionClass;
    }

    public function getPartitionLevelProperty(): string
    {
        return $this->partitionLevelProperty;
    }

    public function getPartitionKeyProperty(): string
    {
        return $this->partitionKeyProperty;
    }

    public function getFullyQualifiedPartitionKeyProperty(): string
    {
        return $this->getName() . '.' . $this->partitionKeyProperty;
    }

    public function getFullyQualifiedPartitionLevelProperty(): string
    {
        return $this->getName() . '.' . $this->partitionLevelProperty;
    }
}
