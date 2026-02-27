<?php

namespace App\Doctrine;

use App\Serializer\ResultSetScalarMappingNameConverter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class ConvertORMQueryBuilderToDBAL
{
    /**
     * @template T
     * @param callable(DBALQueryBuilder $query, array<string|int, string> $fieldAliases): DBALQueryBuilder $buildDBALQuery
     * @param class-string<T> $denormalizeClass
     * @return T[]
     */
    public static function getDenormalizedResult(
        Connection $connection,
        QueryBuilder $query,
        callable $buildDBALQuery,
        string $denormalizeClass,
    ): array {
        $parsedQuery = new Parser($query->getQuery())->parse();
        $queryParametersMapping = $parsedQuery->getParameterMappings();
        $resultSetMapping = $parsedQuery->getResultSetMapping();
        /** @var Collection $dbalQueryParams */
        $dbalQueryParams = collect($query->getQuery()->getParameters())
            ->keyBy(fn(Parameter $param) => $param->getName())
            ->flatMap(fn(Parameter $param) => array_map(
                static fn(int $position) => ['position' => $position, 'value' => $param->getValue(), 'type' => $param->getType()],
                $queryParametersMapping[$param->getName()],
            ));
        $dbalQuery = $buildDBALQuery(
            $connection->createQueryBuilder()->from('(' . $query->getQuery()->getSQL() . ')', 't'),
            array_flip($resultSetMapping->scalarMappings),
        )->setParameters(
            $dbalQueryParams->mapWithKeys(fn(array $param) => [$param['position'] => $param['value']])->toArray(),
            $dbalQueryParams->mapWithKeys(fn(array $param) => [$param['position'] => $param['type']])->toArray(),
        );

        $normalizer = new ObjectNormalizer(nameConverter: new ResultSetScalarMappingNameConverter($resultSetMapping));
        return array_map(
            static fn(array $row) => $normalizer->denormalize($row, $denormalizeClass),
            $dbalQuery->executeQuery()->fetchAllAssociative(),
        );
    }
}
