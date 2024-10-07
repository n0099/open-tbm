<?php

namespace App\Controller;

use App\Helper;
use App\Repository\UserRepository;
use App\Validator\Validator;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;

class UsersQuery extends AbstractController
{
    private int $perPageItems = 200;

    public function __construct(
        private readonly Validator $validator,
        private readonly UserRepository $userRepository,
    ) {}
    
    public function query(): array
    {
        $queryParams = \Safe\json_decode($this->getParameter('query'));
        $paramConstraints = [
            'uid' => new Assert\Type('digit'),
            'name' => new Assert\Type('string'),
            'displayName' => new Assert\Type('string'),
            'gender' => new Assert\Choice(['0', '1', '2', 'NULL']),
        ];
        $this->validator->validate($queryParams, new Assert\Collection($paramConstraints));

        $queriedUsers = collect(collect($queryParams)
            ->reduce(
                function (QueryBuilder $acc, $paramValue, string $paramName) use ($paramConstraints): QueryBuilder {
                    if (!array_key_exists($paramName, $paramConstraints)) {
                        throw new \InvalidArgumentException();
                    }
                    return $paramValue === 'NULL'
                        && in_array($paramName, ['name', 'displayName', 'gender'], true)
                        ? $acc->andWhere("t.$paramName IS NULL")
                        : $acc->andWhere("t.$paramName = :paramValue")
                            ->setParameter('paramValue', $paramValue);
                },
                $this->userRepository->createQueryBuilder('t')
            )
            ?->orderBy('t.id', 'DESC')
            ->setMaxResults($this->perPageItems + 1)
            ->getQuery()->getResult());
        Helper::abortAPIIf(40402, $queriedUsers->isEmpty());

        $hasMorePages = false;
        if ($queriedUsers->count() === $this->perPageItems + 1) {
            $queriedUsers->pop();
            $hasMorePages = true;
        }

        return [
            'pages' => [
                'itemCount' => $queriedUsers->count(),
                'hasMore' => $hasMorePages,
            ],
            'users' => $queriedUsers->all(),
        ];
    }
}
