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

namespace Rekalogika\Analytics\PivotTable\Adapter\ResultSet;

use Rekalogika\Analytics\Contracts\Result\Table;
use Rekalogika\PivotTable\Contracts\Result\ResultSet;

/**
 * @implements \IteratorAggregate<RowAdapter>
 */
final readonly class TableAdapter implements ResultSet, \IteratorAggregate
{
    public function __construct(
        private Table $table,
    ) {}

    #[\Override]
    public function getIterator(): \Traversable
    {
        foreach ($this->table as $row) {
            yield new RowAdapter($row);
        }
    }

    #[\Override]
    public function count(): int
    {
        return $this->table->count();
    }
}
