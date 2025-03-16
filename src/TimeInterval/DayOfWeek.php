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

namespace Rekalogika\Analytics\TimeInterval;

use Rekalogika\Analytics\RecurringTimeInterval;
use Symfony\Contracts\Translation\TranslatorInterface;

enum DayOfWeek: int implements RecurringTimeInterval
{
    use RecurringTimeIntervalEnumTrait;

    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    #[\Override]
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null,
    ): string {
        $dayOfWeek = $this->name;
        $dateTime = new \DateTimeImmutable('next ' . $dayOfWeek);
        $locale = $translator->getLocale();

        $intlDateFormatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            null,
            \IntlDateFormatter::GREGORIAN,
            'EEEE',
        );

        $formatted = $intlDateFormatter->format($dateTime);

        if (!\is_string($formatted)) {
            $formatted = $dayOfWeek;
        }

        return $formatted;
    }
}
