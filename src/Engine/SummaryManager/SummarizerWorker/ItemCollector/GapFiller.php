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

namespace Rekalogika\Analytics\Engine\SummaryManager\SummarizerWorker\ItemCollector;

use Rekalogika\Analytics\Common\Exception\InvalidArgumentException;
use Rekalogika\Analytics\Common\Model\LiteralString;
use Rekalogika\Analytics\Contracts\Model\SequenceMember;
use Rekalogika\Analytics\Engine\Sequence\SequenceUtil;
use Rekalogika\Analytics\Engine\SummaryManager\SummarizerWorker\Output\DefaultDimension;
use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class GapFiller
{
    /**
     * @var array<int,DefaultDimension>
     */
    private array $dimensions;

    private TranslatableInterface $label;

    private string $name;

    /**
     * @param list<DefaultDimension> $dimensions
     */
    private function __construct(
        array $dimensions,
    ) {
        $newDimensions = [];
        $class = null;
        $label = null;
        $name = null;

        foreach ($dimensions as $dimension) {
            $member = $dimension->getMember();

            $label ??= $dimension->getLabel();
            $name ??= $dimension->getName();

            // @todo we skip null value if there is a null value in the dimensions
            if ($member === null) {
                continue;
            }

            // ensure member implements SequenceMember
            if (!$member instanceof SequenceMember) {
                throw new InvalidArgumentException(\sprintf(
                    'Dimension must implement "%s".',
                    SequenceMember::class,
                ));
            }

            // ensure member is of the same class
            if ($class === null) {
                $class = $member::class;
            } elseif ($member::class !== $class) {
                throw new InvalidArgumentException(\sprintf(
                    'Dimension must be of the same class "%s".',
                    $class,
                ));
            }

            /** @psalm-suppress MixedAssignment */
            $newDimensions[spl_object_id($member)] = $dimension;
        }

        $this->dimensions = $newDimensions;

        if ($label === null) {
            $label = new LiteralString('-');
        }

        if ($name === null) {
            $name = '?';
        }

        $this->label = $label;
        $this->name = $name;
    }

    /**
     * @param list<DefaultDimension> $dimensions
     * @return list<DefaultDimension>
     */
    public static function process(array $dimensions): array
    {
        $self = new self($dimensions);

        /**
         * @var list<DefaultDimension>
         * @psalm-suppress InvalidArgument
         */
        return array_values(iterator_to_array($self->getOutput()));
    }

    /**
     * @return iterable<DefaultDimension>
     */
    private function getOutput(): iterable
    {
        $firstDimension = $this->dimensions[array_key_first($this->dimensions) ?? throw new InvalidArgumentException('Dimensions is empty')];
        $lastDimension = $this->dimensions[array_key_last($this->dimensions) ?? throw new InvalidArgumentException('Dimensions is empty')];
        $firstMember = $firstDimension->getMember();
        $lastMember = $lastDimension->getMember();

        if (
            !$firstMember instanceof SequenceMember
            || !$lastMember instanceof SequenceMember
        ) {
            throw new InvalidArgumentException(\sprintf(
                'Dimension must implement "%s".',
                SequenceMember::class,
            ));
        }

        $sequence = SequenceUtil::getSequenceFromMembers($firstMember, $lastMember);

        foreach ($sequence as $current) {
            yield $this->getDimensionFromSequenceMember($current);
        }
    }

    private function getDimensionFromSequenceMember(
        SequenceMember $member,
    ): DefaultDimension {
        $objectId = spl_object_id($member);

        return $this->dimensions[$objectId] ?? new DefaultDimension(
            label: $this->label,
            name: $this->name,
            member: $member,
            rawMember: $member,
            displayMember: $member,
        );
    }
}
