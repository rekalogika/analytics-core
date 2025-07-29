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

namespace Rekalogika\Analytics\Contracts\Collection;

/**
 * @template TKey
 * @template-covariant TValue
 * @extends \Traversable<TKey,TValue>
 */
interface OrderedMapCollection extends \Traversable, \Countable
{
    /**
     * @param TKey $key
     * @return TValue|null
     */
    public function getByKey(mixed $key): mixed;

    /**
     * @return TValue|null
     */
    public function getByIndex(int $index): mixed;

    /**
     * @param TKey $key
     */
    public function hasKey(mixed $key): bool;

    /**
     * @return TValue|null
     */
    public function first(): mixed;

    /**
     * @return TValue|null
     */
    public function last(): mixed;
}
