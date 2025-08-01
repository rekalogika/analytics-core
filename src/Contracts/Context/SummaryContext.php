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

namespace Rekalogika\Analytics\Contracts\Context;

use Rekalogika\Analytics\Contracts\Exception\InvalidArgumentException;
use Rekalogika\Analytics\Contracts\Summary\UserValueTransformer;
use Rekalogika\Analytics\Metadata\Summary\DimensionMetadata;
use Rekalogika\Analytics\Metadata\Summary\MeasureMetadata;
use Rekalogika\Analytics\Metadata\Summary\SummaryMetadata;

final readonly class SummaryContext
{
    /**
     * @param array<string,mixed> $rawInput
     */
    public function __construct(
        private SummaryMetadata $summaryMetadata,
        private array $rawInput,
    ) {}

    public function getSummaryMetadata(): SummaryMetadata
    {
        return $this->summaryMetadata;
    }

    /**
     * @template T of object
     * @param null|class-string<T> $class
     * @return ($class is null ? mixed : T|null)
     */
    public function getUserValue(
        string $property,
        ?string $class = null,
    ): mixed {
        if (!\array_key_exists($property, $this->rawInput)) {
            throw new InvalidArgumentException(\sprintf(
                'Expecting property "%s" to be present in the raw input, but it is not found.',
                $property,
            ));
        }

        /** @psalm-suppress MixedAssignment */
        $rawValue = $this->rawInput[$property];

        $propertyMetadata = $this->summaryMetadata
            ->getProperty($property);

        if ($propertyMetadata instanceof DimensionMetadata) {
            $transformer = $propertyMetadata->getValueResolver();
        } elseif ($propertyMetadata instanceof MeasureMetadata) {
            $transformer = $propertyMetadata->getFunction();
        } else {
            throw new InvalidArgumentException(\sprintf(
                'Getting user value is not supported, the property "%s" is not a dimension or measure.',
                $property,
            ));
        }

        if (!$transformer instanceof UserValueTransformer) {
            throw new InvalidArgumentException(\sprintf(
                'Getting user value is not supported. The value resolver or aggregate function of property "%s" is "%s" which is not an instance of "%s".',
                $property,
                $transformer::class,
                UserValueTransformer::class,
            ));
        }

        $valueTransformerContext = new ValueTransformerContext($propertyMetadata);
        /** @psalm-suppress MixedAssignment */
        $result = $transformer->getUserValue($rawValue, $valueTransformerContext);

        if (
            $class !== null
            && $result !== null
            && (
                !\is_object($result)
                || !is_a($result, $class, true)
            )
        ) {
            throw new InvalidArgumentException(\sprintf(
                'The user value for property "%s" is not an instance of "%s", but "%s".',
                $property,
                $class,
                get_debug_type($result),
            ));
        }

        /** @psalm-suppress MixedReturnStatement */
        return $result;
    }
}
