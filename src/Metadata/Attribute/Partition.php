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

namespace Rekalogika\Analytics\Metadata\Attribute;

use Rekalogika\Analytics\Contracts\Summary\PartitionValueResolver;

/**
 * @template T
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Partition
{
    /**
     * @param PartitionValueResolver<T> $source
     */
    public function __construct(
        private PartitionValueResolver $source,
    ) {}

    /**
     * @return PartitionValueResolver<T>
     */
    public function getSource(): PartitionValueResolver
    {
        return $this->source;
    }
}
