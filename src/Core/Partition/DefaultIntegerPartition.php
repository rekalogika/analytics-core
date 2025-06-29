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

namespace Rekalogika\Analytics\Core\Partition;

use Doctrine\ORM\Mapping\Embeddable;

/**
 * Partition for summarizing source entities with integer primary key.
 */
#[Embeddable]
final class DefaultIntegerPartition extends IntegerPartition
{
    #[\Override]
    public static function getAllLevels(): array
    {
        return [
            55,
            44,
            33,
            22,
            11,
        ];
    }
}
