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

namespace Rekalogika\Analytics\Model\Hierarchy\Trait;

use Doctrine\ORM\Mapping\Column;
use Rekalogika\Analytics\Attribute\LevelProperty;
use Rekalogika\Analytics\DimensionValueResolver\TimeDimensionValueResolver;
use Rekalogika\Analytics\DimensionValueResolver\TimeFormat;
use Rekalogika\Analytics\TimeInterval\Types\YearType;
use Rekalogika\Analytics\TimeInterval\Year;
use Rekalogika\Analytics\Util\TranslatableMessage;

trait YearTrait
{
    abstract public function getTimeZone(): \DateTimeZone;

    #[Column(type: YearType::class, nullable: true)]
    #[LevelProperty(
        level: 600,
        label: new TranslatableMessage('Year'),
        valueResolver: new TimeDimensionValueResolver(TimeFormat::Year),
    )]
    private ?Year $year = null;

    public function getYear(): ?Year
    {
        return $this->year?->withTimeZone($this->getTimeZone());
    }
}
