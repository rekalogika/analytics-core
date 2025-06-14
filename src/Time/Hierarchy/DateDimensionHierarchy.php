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

namespace Rekalogika\Analytics\Time\Hierarchy;

use Doctrine\ORM\Mapping\Embeddable;
use Rekalogika\Analytics\Contracts\Hierarchy\ContextAwareHierarchy;
use Rekalogika\Analytics\Contracts\Metadata\Hierarchy;
use Rekalogika\Analytics\Time\Hierarchy\Trait\ContextAwareHierarchyTrait;
use Rekalogika\Analytics\Time\Hierarchy\Trait\DayTrait;
use Rekalogika\Analytics\Time\Hierarchy\Trait\MonthTrait;
use Rekalogika\Analytics\Time\Hierarchy\Trait\QuarterTrait;
use Rekalogika\Analytics\Time\Hierarchy\Trait\TimeZoneTrait;
use Rekalogika\Analytics\Time\Hierarchy\Trait\WeekTrait;
use Rekalogika\Analytics\Time\Hierarchy\Trait\WeekYearTrait;
use Rekalogika\Analytics\Time\Hierarchy\Trait\YearTrait;

#[Embeddable]
#[Hierarchy([
    [600, 500, 400, 200],
    [700, 300, 200],
])]
final class DateDimensionHierarchy implements ContextAwareHierarchy
{
    use ContextAwareHierarchyTrait;
    use TimeZoneTrait;
    use YearTrait;
    use QuarterTrait;
    use MonthTrait;
    use DayTrait;
    use WeekYearTrait;
    use WeekTrait;
}
