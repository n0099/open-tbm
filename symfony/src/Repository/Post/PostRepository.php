<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository\Post;

use App\Eloquent\Model\Post\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @template T of Post
 * @extends ServiceEntityRepository<T>
 */
abstract class PostRepository extends ServiceEntityRepository
{
    /**
     * @param class-string<T> $postClass
     */
    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $entityManager,
        string $postClass,
        string $tableNameSuffix,
        int $fid,
    ) {
        parent::__construct($registry, $postClass);
        $entityManager->getClassMetadata($postClass)->setPrimaryTable(['name' => "tbmc_f{$fid}_{$tableNameSuffix}"]);
    }
}
