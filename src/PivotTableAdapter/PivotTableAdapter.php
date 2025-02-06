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

namespace Rekalogika\Analytics\PivotTableAdapter;

use Rekalogika\Analytics\PivotTable\BranchNode;
use Rekalogika\Analytics\Query\Result;

final readonly class PivotTableAdapter implements BranchNode
{
    public function __construct(
        private Result $result,
    ) {}

    #[\Override]
    public function getKey(): string
    {
        return '';
    }

    #[\Override]
    public function getLegend(): mixed
    {
        return null;
    }

    #[\Override]
    public function getItem(): mixed
    {
        return null;
    }

    #[\Override]
    public function getChildren(): iterable
    {
        foreach ($this->result as $item) {
            if ($item->isLeaf()) {
                yield new PivotTableLeaf($item);
            } else {
                yield new PivotTableBranch($item);
            }
        }
    }
}
