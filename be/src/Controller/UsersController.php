<?php

namespace App\Controller;

use App\Utils;
use App\PostsQuery\ParamsValidator;
use App\PostsQuery\QueryResult;
use App\Repository\UserRepository;
use App\Validator\Validator;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

class UsersController extends AbstractController
{
    private int $perPageItems = 200;

    public function __construct(
        private readonly Validator $validator,
        private readonly UserRepository $userRepository,
        private readonly QueryResult $queryResult,
    ) {}

    #[Route('/api/users')]
    public function query(Request $request): array
    {
        $queryParams = $request->query->all();
        $paramConstraints = [
            'uid' => new Assert\Type('digit'),
            'name' => new Assert\Type('string'),
            'displayName' => new Assert\Type('string'),
            'gender' => new Assert\Choice(ParamsValidator::USER_GENDER_VALUES),
        ];
        $this->validator->validate($queryParams, new Assert\Collection($paramConstraints, allowMissingFields: true));

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = collect($queryParams)
            ->reduce(
                function (QueryBuilder $queryBuilder, $paramValue, string $paramName) use ($paramConstraints): QueryBuilder {
                    if (!array_key_exists($paramName, $paramConstraints)) {
                        throw new \InvalidArgumentException();
                    }
                    return $paramValue === 'NULL'
                        && in_array($paramName, ['name', 'displayName', 'gender'], true)
                        ? $queryBuilder->andWhere("t.$paramName IS NULL")
                        : $queryBuilder->andWhere("t.$paramName = :$paramName")
                            ->setParameter($paramName, $paramValue);
                },
                $this->userRepository->createQueryBuilder('t'),
            );
        $queryBuilder = $queryBuilder->orderBy('t.uid', 'DESC');

        ['result' => $result, 'hasMorePages' => $hasMorePages] = $this->queryResult->getQueryResult(
            $queryBuilder->getEntityManager(),
            $queryBuilder,
            new Parser($queryBuilder->getQuery())->parse()->getResultSetMapping(),
            $this->perPageItems,
        );
        $resultCount = $result->count();
        Utils::abortAPIIf(40402, $resultCount === 0);

        return [
            'pages' => [
                'itemCount' => $resultCount,
                'hasMore' => $hasMorePages,
            ],
            'users' => $result,
        ];
    }
}
