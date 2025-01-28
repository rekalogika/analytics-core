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

namespace Rekalogika\Analytics;

use Rekalogika\Analytics\SummaryManager\Query\QueryContext;

/**
 * Classifies the partitioning key of the entity into the corresponding
 * partition key in the summary table
 */
interface PartitionKeyClassifier
{
    public function getDQL(
        PartitionValueResolver $input,
        int $level,
        QueryContext $context,
    ): string;
}
