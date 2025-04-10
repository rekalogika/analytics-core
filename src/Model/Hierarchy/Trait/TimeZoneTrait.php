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

trait TimeZoneTrait
{
    private \DateTimeZone $timeZone;

    public function setTimeZone(\DateTimeZone $timeZone): void
    {
        $this->timeZone = $timeZone;
    }

    public function getTimeZone(): \DateTimeZone
    {
        return $this->timeZone;
    }
}
