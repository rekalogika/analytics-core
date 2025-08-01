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

namespace Rekalogika\Analytics\Time\Bin\MonthBasedWeek;

use Doctrine\DBAL\Types\Types;
use Rekalogika\Analytics\Contracts\Exception\BadMethodCallException;
use Rekalogika\Analytics\Time\Bin\Trait\TimeBinTrait;
use Rekalogika\Analytics\Time\MonotonicTimeBin;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Month based week-date (YYYYMMWD).
 *
 * A week starts on Monday and ends on Sunday. The first week of the month is
 * the week that contains the first Thursday of the month. The first week of the
 * month can extend into the previous month, and the last week of the month can
 * extend into the next month.
 */
final class MonthBasedWeekDate implements MonotonicTimeBin
{
    use TimeBinTrait;

    public const TYPE = Types::INTEGER;

    private readonly \DateTimeImmutable $start;

    private readonly \DateTimeImmutable $end;

    private function __construct(
        int $databaseValue,
        \DateTimeZone $timeZone,
    ) {
        $this->databaseValue = $databaseValue;

        $string = \sprintf('%08d', $databaseValue);

        $y = (int) substr($string, 0, 4); // Year
        $m = (int) substr($string, 4, 2); // Month
        $w = (int) substr($string, 6, 1); // Week of the month (1-5)
        $d = (int) substr($string, 7, 1); // Day of the week (1-7, where 1 is Monday)

        /** @var \DateTimeImmutable */
        $start = (new \DateTimeImmutable())
            ->setTimezone($timeZone)
            ->setDate($y, $m, 1) // Start from the first day of the month
            ->setTime(0, 0, 0)
            ->modify('first thursday of this month') // Adjust to the first Monday
            ->modify('previous monday') // Move to the Monday of the week
            ->modify(\sprintf('+%d weeks', $w - 1)) // Move to the correct week
            ->modify(\sprintf('+%d days', $d - 1)); // Move to the correct day

        /** @var \DateTimeImmutable */
        $end = $start->modify('+1 day');

        $this->start = $start;
        $this->end = $end;
    }

    #[\Override]
    public static function getDQLExpression(
        string $sourceExpression,
        \DateTimeZone $sourceTimeZone,
        \DateTimeZone $summaryTimeZone,
    ): string {
        throw new BadMethodCallException('Not implemented yet.');
    }

    #[\Override]
    public static function createFromDateTime(
        \DateTimeInterface $dateTime,
    ): static {
        $thursdayThisWeek = \DateTimeImmutable::createFromInterface($dateTime)
            ->modify('this thursday');

        $thursdayDateOfMonth = (int) $thursdayThisWeek->format('d');
        $weekOfMonth = (int) ceil($thursdayDateOfMonth / 7);
        $month = (int) $thursdayThisWeek->format('m');
        $year = (int) $thursdayThisWeek->format('Y');
        $dayOfWeek = (int) $dateTime->format('N'); // 1 (Monday) to 7 (Sunday)

        $databaseValue = (int) \sprintf(
            '%04d%02d%01d%01d',
            $year,
            $month,
            $weekOfMonth,
            $dayOfWeek,
        );

        return self::create(
            $databaseValue,
            $dateTime->getTimezone(),
        );
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->start->format('o-\WW');
    }

    #[\Override]
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null,
    ): string {
        $formatter = new \IntlDateFormatter(
            locale: $locale,
            dateType: \IntlDateFormatter::MEDIUM,
            timeType: \IntlDateFormatter::NONE,
            timezone: $this->start->getTimezone(),
        );

        return \sprintf(
            '%s - %s',
            $formatter->format($this->start),
            $formatter->format($this->end->modify('-1 day')),
        );
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
    //     return (int) $this->start->format('oW');
    // }

    // public function getEndDatabaseValue(): int
    // {
    //     return (int) $this->end->format('oW');
    // }

    // private function getContainingWeekYear(): WeekYear
    // {
    //     return WeekYear::createFromDatabaseValue(
    //         (int) $this->start->format('o'),
    //         $this->start->getTimezone(),
    //     );
    // }

    #[\Override]
    public function getNext(): static
    {
        return self::create(
            (int) $this->end->format('oW'),
            $this->end->getTimezone(),
        );
    }

    #[\Override]
    public function getPrevious(): static
    {
        $previousWeek = $this->start->modify('-1 week');

        return self::create(
            (int) $previousWeek->format('oW'),
            $previousWeek->getTimezone(),
        );
    }
}
