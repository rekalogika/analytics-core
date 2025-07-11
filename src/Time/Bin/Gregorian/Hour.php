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

namespace Rekalogika\Analytics\Time\Bin\Gregorian;

use Doctrine\DBAL\Types\Types;
use Rekalogika\Analytics\Time\Bin\Trait\RekalogikaTimeBinDQLExpressionTrait;
use Rekalogika\Analytics\Time\Bin\Trait\TimeBinTrait;
use Rekalogika\Analytics\Time\MonotonicTimeBin;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Hour (YYYYMMDDHH)
 */
final class Hour implements MonotonicTimeBin
{
    use TimeBinTrait;
    use RekalogikaTimeBinDQLExpressionTrait;

    public const TYPE = Types::INTEGER;

    private readonly \DateTimeImmutable $start;

    private readonly \DateTimeImmutable $end;

    private function __construct(
        int $databaseValue,
        \DateTimeZone $timeZone,
    ) {
        $this->databaseValue = $databaseValue;

        $ymdh = \sprintf('%010d', $databaseValue);

        $y = (int) substr($ymdh, 0, 4);
        $m = (int) substr($ymdh, 4, 2);
        $d = (int) substr($ymdh, 6, 2);
        $h = (int) substr($ymdh, 8, 2);

        $this->start = new \DateTimeImmutable(
            \sprintf('%04d-%02d-%02d %02d:00:00', $y, $m, $d, $h),
            $timeZone,
        );

        $this->end = $this->start->modify('+1 hour');
    }

    #[\Override]
    private static function getSqlToCharArgument(): string
    {
        return 'YYYYMMDDHH24';
    }

    #[\Override]
    public static function createFromDateTime(
        \DateTimeInterface $dateTime,
    ): static {
        return self::create(
            (int) $dateTime->format('YmdH'),
            $dateTime->getTimezone(),
        );
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->start->format('Y-m-d H:00');
    }

    #[\Override]
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null,
    ): string {
        return $this->start->format('Y-m-d H:00');
    }

    #[\Override]
    public function getStart(): \DateTimeInterface
    {
        return $this->start;
    }

    #[\Override]
    public function getEnd(): \DateTimeInterface
    {
        return $this->end;
    }

    // public function getStartDatabaseValue(): int
    // {
    //     return (int) $this->start->format('YmdH');
    // }

    // public function getEndDatabaseValue(): int
    // {
    //     return (int) $this->end->format('YmdH');
    // }

    // private function getContainingDate(): Date
    // {
    //     return Date::createFromDatabaseValue(
    //         (int) $this->start->format('Ymd'),
    //         $this->start->getTimezone(),
    //     );
    // }

    // private function getContainingWeekDate(): WeekDate
    // {
    //     return WeekDate::createFromDatabaseValue(
    //         (int) $this->start->format('oWw'),
    //         $this->start->getTimezone(),
    //     );
    // }

    #[\Override]
    public function getNext(): static
    {
        $next = $this->start->modify('+1 hour');

        return self::create(
            (int) $next->format('YmdH'),
            $next->getTimezone(),
        );
    }

    #[\Override]
    public function getPrevious(): static
    {
        $previous = $this->start->modify('-1 hour');

        return self::create(
            (int) $previous->format('YmdH'),
            $previous->getTimezone(),
        );
    }
}
