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

namespace Rekalogika\Analytics\Contracts\Summary;

interface PartitionValueResolver extends ValueResolver
{
    /**
     * Unlike ValueResolver, here we return one and only one value.
     *
     * @return list{string}
     */
    #[\Override]
    public function getInvolvedProperties(): array;

    public function transformSourceValueToSummaryValue(mixed $value): mixed;

    /**
     * Transform a specific summary table value to source value.
     */
    public function transformSummaryValueToSourceValue(mixed $value): int|string;
}
