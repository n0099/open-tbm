<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Repository\Post;

use App\Entity\Post\Post;
use App\Helper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @template T of Post
 * @extends ServiceEntityRepository<T>
 */
abstract class PostRepository extends ServiceEntityRepository
{
    abstract protected function getTableNameSuffix(): string;

    /**
     * @param class-string<T> $postClass
     */
    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $entityManager,
        string $postClass,
        int $fid,
    ) {
        parent::__construct($registry, $postClass);
        $entityManager->getClassMetadata($postClass)->setPrimaryTable([
            'name' => "\"tbmc_f{$fid}_" . $this->getTableNameSuffix() . '"'
        ]);
    }

    public function selectCurrentAndParentPostID(): QueryBuilder
    {
        return $this->createQueryBuilder('t')->addSelect(collect(Helper::POST_TYPE_TO_ID)
            ->slice(0, array_search($this->getTableNameSuffix(), Helper::POST_TYPES, true) + 1)
            ->values()
            ->map(static fn(string $postIDField) => "t.$postIDField")
            ->all());
    }
}
