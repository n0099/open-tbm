<?php

namespace App\DTO\Post;

use App\Entity\Post\Reply as ReplyEntity;
use Illuminate\Support\Collection;
use Symfony\Component\Serializer\Attribute\Ignore;

class Reply extends ReplyEntity implements SortablePost
{
    use Post { fromEntity as private fromPostEntity; }
    use PostWithContent;

    #[Ignore] public int $fid;
    public Collection $subReplies;
    public bool $isMatchQuery; // https://github.com/php/php-src/issues/18391

    public static function fromEntity(ReplyEntity $entity): self
    {
        $dto = self::fromPostEntity($entity);
        $dto->tid = $entity->tid;
        $dto->pid = $entity->pid;
        $dto->floor = $entity->floor;
        $dto->subReplyCount = $entity->subReplyCount;
        $dto->isFold = $entity->isFold;
        $dto->geolocation = $entity->geolocation;
        $dto->signatureId = $entity->signatureId;
        return $dto;
    }
}
