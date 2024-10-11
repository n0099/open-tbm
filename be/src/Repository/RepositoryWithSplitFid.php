<?php

namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @template T
 * @extends BaseRepository<T>
 */
abstract class RepositoryWithSplitFid extends BaseRepository
{
    abstract protected function getTableNameSuffix(): string;

    /** @param class-string<T> $postRepositoryClass */
    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $entityManager,
        string $postRepositoryClass,
        private readonly int $fid,
    ) {
        parent::__construct($registry, $postRepositoryClass);
        $entityManager->getClassMetadata($postRepositoryClass)->setPrimaryTable([
            'name' => "\"tbmc_f{$fid}_" . $this->getTableNameSuffix() . '"',
        ]);
    }

    public function getFid(): int
    {
        return $this->fid;
    }
}
