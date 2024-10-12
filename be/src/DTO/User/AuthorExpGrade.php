<?php

namespace App\DTO\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
readonly class AuthorExpGrade
{
    public function __construct(
        #[ORM\Column, ORM\Id, Ignore]
        public int $uid,
        #[ORM\Column]
        public int $discoveredAt,
        #[ORM\Column]
        public int $authorExpGrade,
    ) {}
}
