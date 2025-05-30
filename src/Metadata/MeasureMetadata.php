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

namespace Rekalogika\Analytics\Metadata;

use Rekalogika\Analytics\Contracts\Summary\AggregateFunction;
use Rekalogika\Analytics\Exception\MetadataException;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class MeasureMetadata
{
    /**
     * @param non-empty-array<class-string,AggregateFunction> $function
     */
    public function __construct(
        private array $function,
        private string $summaryProperty,
        private TranslatableInterface $label,
        private null|TranslatableInterface $unit,
        private ?string $unitSignature,
        private ?SummaryMetadata $summaryMetadata = null,
    ) {}

    public function withSummaryMetadata(SummaryMetadata $summaryMetadata): self
    {
        return new self(
            function: $this->function,
            summaryProperty: $this->summaryProperty,
            label: $this->label,
            unit: $this->unit,
            unitSignature: $this->unitSignature,
            summaryMetadata: $summaryMetadata,
        );
    }

    /**
     * @return array<class-string,AggregateFunction>
     */
    public function getFunction(): array
    {
        return $this->function;
    }

    public function getFirstFunction(): AggregateFunction
    {
        $function = $this->function;

        return reset($function);
    }

    public function getSummaryProperty(): string
    {
        return $this->summaryProperty;
    }

    public function getLabel(): TranslatableInterface
    {
        return $this->label;
    }

    public function getUnit(): null|TranslatableInterface
    {
        return $this->unit;
    }

    public function getUnitSignature(): ?string
    {
        return $this->unitSignature;
    }

    /**
     * @return array<class-string,list<string>>
     */
    public function getInvolvedProperties(): array
    {
        $properties = [];

        foreach ($this->function as $class => $aggregateFunction) {
            foreach ($aggregateFunction->getInvolvedProperties() as $property) {
                $properties[$class][] = $property;
            }
        }

        $uniqueProperties = [];

        foreach ($properties as $class => $listOfProperties) {
            $uniqueProperties[$class] = array_values(array_unique($listOfProperties));
        }

        return $uniqueProperties;
    }

    public function getSummaryMetadata(): SummaryMetadata
    {
        if ($this->summaryMetadata === null) {
            throw new MetadataException('Summary table metadata is not set');
        }

        return $this->summaryMetadata;
    }
}
