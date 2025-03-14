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

namespace Rekalogika\Analytics\TimeDimensionHierarchy;

use Symfony\Contracts\Translation\TranslatorInterface;

final class Week implements Interval
{
    use CacheTrait;

    private readonly \DateTimeImmutable $start;

    private readonly \DateTimeImmutable $end;

    private function __construct(
        int $databaseValue,
        \DateTimeZone $timeZone,
    ) {
        $this->databaseValue = $databaseValue;

        $string = \sprintf('%06d', $databaseValue);

        $y = (int) substr($string, 0, 4);
        $w = (int) substr($string, 4, 2);

        $this->start = (new \DateTimeImmutable())
            ->setTimezone($timeZone)
            ->setISODate($y, $w)
            ->setTime(0, 0, 0);

        $this->end = $this->start->modify('+1 week');
    }

    public function getHierarchyLevel(): int
    {
        return 300;
    }

    // #[\Override]
    // public function getContainingIntervals(): array
    // {
    //     return [
    //         $this->getContainingWeekYear(),
    //     ];
    // }

    #[\Override]
    public static function createFromDateTime(
        \DateTimeInterface $dateTime,
    ): static {
        return self::create(
            (int) $dateTime->format('oW'),
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
        return $translator->trans(
            id: '{start, date} - {end, date}',
            parameters: [
                'start' => $this->start,
                'end' => $this->end->modify('-1 day'),
            ],
            domain: 'rekalogika_analytics+intl-icu',
            locale: $locale,
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
}
