<?php

namespace App\DTO\Post;

use App\DTO\TimestampedDTO;
use Symfony\Component\Serializer\Attribute\Ignore;

trait Post
{
    use TimestampedDTO { fromEntity as private fromTimestampedEntity; }

    // public bool $isMatchQuery; // https://github.com/php/php-src/issues/18391
    #[Ignore] public mixed $sortingKey = null;

    public function setIsMatchQuery(bool $value): self
    {
        $this->isMatchQuery = $value;
        return $this;
    }

    public function setSortingKey(mixed $value): self
    {
        $this->sortingKey = $value;
        return $this;
    }

    public static function fromEntity(\App\Entity\Post\Post $entity): self
    {
        $dto = self::fromTimestampedEntity($entity);
        $dto->fid = $entity->fid;
        $dto->tid = $entity->tid;
        $dto->authorUid = $entity->authorUid;
        $dto->postedAt = $entity->postedAt;
        $dto->lastSeenAt = $entity->lastSeenAt;
        $dto->agreeCount = $entity->agreeCount;
        $dto->disagreeCount = $entity->disagreeCount;
        return $dto;
    }
}
