<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;

/** @extends BaseRepository<User> */
class UserRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function getUsers(\ArrayAccess $usersId): array
    {
        $dql = 'SELECT t FROM App\Entity\User t WHERE t.uid IN (:usersId)';
        return $this->getQueryResultWithSingleParam($dql, 'usersId', $usersId);
    }
}
