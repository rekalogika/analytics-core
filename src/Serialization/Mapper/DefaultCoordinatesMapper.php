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

namespace Rekalogika\Analytics\Serialization\Mapper;

use Doctrine\Common\Collections\Expr\Expression;
use Rekalogika\Analytics\Contracts\Dto\CoordinatesDto;
use Rekalogika\Analytics\Contracts\Dto\ExpressionDto;
use Rekalogika\Analytics\Contracts\Exception\UnexpectedValueException;
use Rekalogika\Analytics\Contracts\Result\Cell;
use Rekalogika\Analytics\Contracts\Result\Coordinates;
use Rekalogika\Analytics\Contracts\Serialization\CoordinatesMapper;
use Rekalogika\Analytics\Contracts\Serialization\ValueSerializer;
use Rekalogika\Analytics\Contracts\SummaryManager;
use Rekalogika\Analytics\Metadata\Summary\SummaryMetadataFactory;
use Rekalogika\Analytics\Serialization\Implementation\NullCell;
use Rekalogika\Analytics\Serialization\Mapper\Implementation\ChainMapper;

final readonly class DefaultCoordinatesMapper implements CoordinatesMapper
{
    /**
     * @var Mapper<object,object>
     */
    private Mapper $mapper;

    public function __construct(
        private ValueSerializer $valueSerializer,
        private SummaryManager $summaryManager,
        private SummaryMetadataFactory $summaryMetadataFactory,
    ) {
        $this->mapper = new ChainMapper($this->valueSerializer);
    }

    #[\Override]
    public function toDto(Coordinates|Cell $input): CoordinatesDto
    {
        if ($input instanceof Cell) {
            $input = $input->getCoordinates();
        }

        $class = $input->getSummaryClass();

        $members = [];

        foreach ($input as $name => $dimension) {
            if ($name === '@values') {
                continue;
            }

            $dimensionName = $dimension->getName();
            /** @psalm-suppress MixedAssignment */
            $dimensionMember = $dimension->getRawMember();

            $serializedValue = $this->valueSerializer->serialize(
                class: $class,
                dimension: $dimensionName,
                value: $dimensionMember,
            );

            $members[$dimensionName] = $serializedValue;
        }

        $predicate = $input->getPredicate();

        if ($predicate !== null) {
            $mapperContext = new MapperContext(
                summaryClass: $class,
            );

            $predicate = $this->mapper->toDto($predicate, $mapperContext);

            if (!$predicate instanceof ExpressionDto) {
                throw new UnexpectedValueException('Expected ExpressionDto, got ' . \get_class($predicate));
            }
        }

        return new CoordinatesDto(
            members: $members,
            predicate: $predicate,
        );
    }

    /**
     * @todo limit dimensions member in query for efficiency
     */
    #[\Override]
    public function fromDto(string $summaryClass, CoordinatesDto $dto): Cell
    {
        // create query
        $query = $this->summaryManager
            ->createQuery()
            ->from($summaryClass);

        // add where condition
        $predicateDto = $dto->getPredicate();
        $predicate = null;

        if ($predicateDto !== null) {
            $mapperContext = new MapperContext(
                summaryClass: $summaryClass,
            );

            $predicate = $this->mapper->fromDto($predicateDto, $mapperContext);

            if (!$predicate instanceof Expression) {
                throw new UnexpectedValueException('Expected Expression, got ' . \get_class($predicate));
            }

            $query->dice($predicate);
        }

        // add group by
        $dimensionMembers = [];

        foreach ($dto->getMembers() as $dimensionName => $serializedValue) {
            /** @psalm-suppress MixedAssignment */
            $rawMember = $this->valueSerializer->deserialize(
                class: $summaryClass,
                dimension: $dimensionName,
                identifier: $serializedValue,
            );

            $query->addDimension($dimensionName);

            /** @psalm-suppress MixedAssignment */
            $dimensionMembers[$dimensionName] = $rawMember;
        }

        $cube = $query->getResult();

        /** @psalm-suppress MixedAssignment */
        foreach ($dimensionMembers as $dimensionName => $rawMember) {
            $cube = $cube->slice($dimensionName, $rawMember);

            if ($cube === null) {
                $metadata = $this->summaryMetadataFactory
                    ->getSummaryMetadata($summaryClass);

                return new NullCell(
                    summaryMetadata: $metadata,
                    dimensionMembers: $dimensionMembers,
                    condition: $predicate,
                );
            }
        }

        return $cube;
    }
}
