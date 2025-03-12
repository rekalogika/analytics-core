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

namespace Rekalogika\Analytics\SummaryManager\SummarizerWorker\Output;

use Rekalogika\Analytics\Query\Unit;
use Rekalogika\Analytics\SummaryManager\SummarizerWorker\Model\ResultValue;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DefaultUnit implements Unit
{
    public function __construct(
        private string|TranslatableInterface $label,
        private string $signature,
    ) {}

    public static function createFromResultValue(ResultValue $resultValue): ?self
    {
        if ($resultValue->getUnit() === null) {
            return null;
        }

        return new self(
            label: $resultValue->getUnit(),
            signature: $resultValue->getUnitSignature()
                ?? throw new \LogicException('Unit signature is required'),
        );
    }

    #[\Override]
    public function getSignature(): string
    {
        return $this->signature;
    }

    #[\Override]
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null,
    ): string {
        if ($this->label instanceof TranslatableInterface) {
            return $this->label->trans($translator, $locale);
        } else {
            return $this->label;
        }
    }
}
