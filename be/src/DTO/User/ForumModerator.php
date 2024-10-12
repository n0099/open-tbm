<?php

namespace App\DTO\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
readonly class ForumModerator
{
    public function __construct(
        #[ORM\Column, ORM\Id, Ignore]
        public string $portrait,
        #[ORM\Column]
        public int $discoveredAt,
        #[ORM\Column]
        public string $moderatorTypes,
    ) {}
}
