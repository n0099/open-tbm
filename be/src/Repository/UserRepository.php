<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }
    
    public function getUsers(array $usersId): array
    {
        return $this->getEntityManager()
            ->createQuery(/** @lang DQL */'SELECT t FROM App\Entity\User t WHERE t.uid IN (:usersId)')
            ->setParameter('usersId', $usersId)->getResult();         
    }
}
