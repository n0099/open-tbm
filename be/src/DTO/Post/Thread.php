<?php

namespace App\DTO\Post;

use App\Entity\Post\Thread as ThreadEntity;
use Illuminate\Support\Collection;

class Thread extends ThreadEntity implements SortablePost
{
    use Post { fromEntity as private fromPostEntity; }

    public Collection $replies;
    public bool $isMatchQuery; // https://github.com/php/php-src/issues/18391

    public static function fromEntity(ThreadEntity $entity): self
    {
        $dto = self::fromPostEntity($entity);
        $dto->tid = $entity->tid;
        $dto->threadType = $entity->threadType;
        $dto->stickyType = $entity->stickyType;
        $dto->topicType = $entity->topicType;
        $dto->isGood = $entity->isGood;
        $dto->title = $entity->title;
        $dto->latestReplyPostedAt = $entity->latestReplyPostedAt;
        $dto->latestReplierId = $entity->latestReplierId;
        $dto->replyCount = $entity->replyCount;
        $dto->viewCount = $entity->viewCount;
        $dto->shareCount = $entity->shareCount;
        $dto->zan = $entity->zan;
        $dto->geolocation = $entity->geolocation;
        $dto->authorPhoneType = $entity->authorPhoneType;
        return $dto;
    }
}
