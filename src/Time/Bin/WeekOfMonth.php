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

namespace Rekalogika\Analytics\Time\Bin;

use Rekalogika\Analytics\Time\Bin\Trait\RecurringTimeBinTrait;
use Rekalogika\Analytics\Time\RecurringTimeBin;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WeekOfMonth: int implements RecurringTimeBin
{
    use RecurringTimeBinTrait;

    case Week1 = 1;
    case Week2 = 2;
    case Week3 = 3;
    case Week4 = 4;
    case Week5 = 5;

    #[\Override]
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null,
    ): string {
        return 'W' . $this->value;
    }
}
