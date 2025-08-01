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

namespace Rekalogika\Analytics\Time\Dimension\Group;

use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Embedded;
use Rekalogika\Analytics\Contracts\Translation\TranslatableMessage;
use Rekalogika\Analytics\Core\Entity\BaseDimensionGroup;
use Rekalogika\Analytics\Core\GroupingStrategy\GroupingSetStrategy;
use Rekalogika\Analytics\Core\ValueResolver\Noop;
use Rekalogika\Analytics\Metadata\Attribute\Dimension;
use Rekalogika\Analytics\Metadata\Attribute\DimensionGroup;
use Rekalogika\Analytics\Time\Dimension\System\GregorianDateWithHour;
use Rekalogika\Analytics\Time\Dimension\System\IsoWeekDateWithHour;

#[Embeddable()]
#[DimensionGroup(
    groupingStrategy: new GroupingSetStrategy(),
)]
class TimeGroup extends BaseDimensionGroup
{
    #[Embedded()]
    #[Dimension(
        label: new TranslatableMessage('Civil'),
        source: new Noop(),
    )]
    private ?GregorianDateWithHour $civil = null;

    #[Embedded()]
    #[Dimension(
        label: new TranslatableMessage('ISO Week'),
        source: new Noop(),
    )]
    private ?IsoWeekDateWithHour $isoWeek = null;

    public function getCivil(): ?GregorianDateWithHour
    {
        return $this->civil;
    }

    public function getIsoWeek(): ?IsoWeekDateWithHour
    {
        return $this->isoWeek;
    }
}
