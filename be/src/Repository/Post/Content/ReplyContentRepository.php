<?php

namespace App\Repository\Post\Content;

use App\Entity\Post\Content\ReplyContent;
use App\Repository\Post\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @extends PostRepository<ReplyContent>
 */
#[Exclude]
class ReplyContentRepository extends PostRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager, int $fid)
    {
        parent::__construct($registry, $entityManager, ReplyContent::class, $fid);
    }

    protected function getTableNameSuffix(): string
    {
        return 'reply_content';
    }

    public function getPosts(\ArrayAccess $postsId): array
    {
        return $this->createQueryWithParam(
            /** @lang DQL */'SELECT t FROM App\Entity\Post\Content\ReplyContent t WHERE t.pid IN (:pid)',
            'pid',
            $postsId
        )->getResult();
    }
}
