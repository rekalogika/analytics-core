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

namespace Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output;

use Rekalogika\Analytics\Contracts\Measure;
use Rekalogika\Analytics\Contracts\NormalRow;
use Rekalogika\Analytics\Contracts\Tuple;
use Rekalogika\Analytics\Util\DimensionUtil;

final readonly class DefaultNormalRow implements NormalRow
{
    public function __construct(
        private Tuple $tuple,
        private Measure $measure,
        private string $groupings,
    ) {}

    #[\Override]
    public function getTuple(): Tuple
    {
        return $this->tuple;
    }

    #[\Override]
    public function getMeasure(): Measure
    {
        return $this->measure;
    }

    public function getGroupings(): string
    {
        return $this->groupings;
    }

    /**
     * @param array<string,int<0,max>> $measures
     * @return -1|0|1
     */
    public static function compare(self $row1, self $row2, array $measures): int
    {
        $dimensions1 = $row1->getTuple();
        $dimensions2 = $row2->getTuple();

        foreach ($dimensions1 as $key => $value1) {
            $value2 = $dimensions2->get($key);

            if ($key === '@values') {
                break;
            }

            if (!$value1 instanceof DefaultDimension || !$value2 instanceof DefaultDimension) {
                throw new \RuntimeException('Only DefaultDimension is supported');
            }

            if (!DimensionUtil::isSame($value1, $value2)) {
                return 0;
            }
        }

        $measure1Order = $measures[$row1->getMeasure()->getKey()]
            ?? throw new \RuntimeException('Measure not found');

        $measure2Order = $measures[$row2->getMeasure()->getKey()]
            ?? throw new \RuntimeException('Measure not found');

        return $measure1Order <=> $measure2Order;
    }

    public function hasSameTuple(self $other): bool
    {
        return $this->tuple->isSame($other->tuple);
    }
}
