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

namespace Rekalogika\Analytics\Attribute;

use Rekalogika\Analytics\Contracts\Summary\HierarchicalDimensionValueResolver;
use Symfony\Contracts\Translation\TranslatableInterface;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class LevelProperty
{
    public function __construct(
        private int $level,
        private HierarchicalDimensionValueResolver $valueResolver,
        private null|string|TranslatableInterface $label = null,
        private null|string|TranslatableInterface $nullLabel = null,
    ) {}

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getLabel(): null|string|TranslatableInterface
    {
        return $this->label;
    }

    public function getValueResolver(): HierarchicalDimensionValueResolver
    {
        return $this->valueResolver;
    }

    public function getNullLabel(): null|string|TranslatableInterface
    {
        return $this->nullLabel;
    }
}
