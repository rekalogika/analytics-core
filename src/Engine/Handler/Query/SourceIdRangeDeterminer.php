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

namespace Rekalogika\Analytics\Engine\Handler\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Rekalogika\Analytics\Contracts\Summary\HasQueryBuilderModifier;
use Rekalogika\Analytics\Contracts\Summary\PartitionValueResolver;
use Rekalogika\Analytics\Engine\Infrastructure\AbstractQuery;
use Rekalogika\Analytics\Metadata\Summary\SummaryMetadata;
use Rekalogika\Analytics\SimpleQueryBuilder\SimpleQueryBuilder;

final class SourceIdRangeDeterminer extends AbstractQuery
{
    /**
     * @var PartitionValueResolver<mixed>
     */
    private readonly PartitionValueResolver $valueResolver;

    /**
     * @param class-string $class
     */
    public function __construct(
        string $class,
        EntityManagerInterface $entityManager,
        SummaryMetadata $summaryMetadata,
    ) {
        $summaryClass = $summaryMetadata->getSummaryClass();

        $simpleQueryBuilder = new SimpleQueryBuilder(
            entityManager: $entityManager,
            from: $class,
            alias: 'root',
        );

        if (is_a($summaryClass, HasQueryBuilderModifier::class, true)) {
            $summaryClass::modifyQueryBuilder($simpleQueryBuilder->getQueryBuilder());
        }

        parent::__construct($simpleQueryBuilder);

        $this->valueResolver = $summaryMetadata
            ->getPartition()
            ->getSource();
    }

    public function getMinKey(): int|string|null
    {
        $partitionProperty = $this->valueResolver
            ->getInvolvedProperties()[0];

        $field = \sprintf(
            'root.%s',
            $partitionProperty,
        );

        $query = $this->getSimpleQueryBuilder()
            ->select($field)
            ->orderBy($field, 'ASC')
            ->setMaxResults(1)
            ->getQuery();

        try {
            $result = $query->getSingleScalarResult();
        } catch (NoResultException) {
            $result = null;
        }

        if ($result === null || \is_int($result)) {
            return $result;
        }

        if (is_numeric($result)) {
            return (int) $result;
        }

        return (string) $result;
    }

    public function getMaxKey(): int|string|null
    {
        $partitionProperty = $this->valueResolver
            ->getInvolvedProperties()[0];

        $field = \sprintf(
            'root.%s',
            $partitionProperty,
        );

        $query = $this->getSimpleQueryBuilder()
            ->select($field)
            ->orderBy($field, 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        try {
            $result = $query->getSingleScalarResult();
        } catch (NoResultException) {
            $result = null;
        }

        if ($result === null || \is_int($result)) {
            return $result;
        }

        if (is_numeric($result)) {
            return (int) $result;
        }

        return (string) $result;
    }
}
