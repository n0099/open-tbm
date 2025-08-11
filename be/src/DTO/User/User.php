<?php

namespace App\DTO\User;

use App\DTO\TimestampedDTO;
use App\Entity\User as UserEntity;

class User extends UserEntity
{
    use TimestampedDTO { fromEntity as private fromTimestampedEntity; }

    /** @var array{int, array{forumModerator: ForumModerator, authorExpGrade: AuthorExpGrade}} */
    public array $forumSpecific;

    public static function fromEntity(UserEntity $entity): self
    {
        $dto = self::fromTimestampedEntity($entity);
        $dto->uid = $entity->uid;
        $dto->name = $entity->name;
        $dto->displayName = $entity->displayName;
        $dto->portrait = $entity->portrait;
        $dto->portraitUpdatedAt = $entity->portraitUpdatedAt;
        $dto->gender = $entity->gender;
        $dto->fansNickname = $entity->fansNickname;
        $dto->icon = $entity->icon;
        $dto->ipGeolocation = $entity->ipGeolocation;
        return $dto;
    }
}
