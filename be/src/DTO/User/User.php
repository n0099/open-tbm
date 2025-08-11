<?php

namespace App\DTO\User;

use App\Entity\User as UserEntity;

class User extends UserEntity
{
    /** @var array{int, array{forumModerator: ForumModerator, authorExpGrade: AuthorExpGrade}} */
    public array $forumSpecific;
}
