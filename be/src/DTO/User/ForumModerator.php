<?php

namespace App\DTO\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
readonly class ForumModerator
{
    public function __construct(
        #[ORM\Column, ORM\Id]
        private string $portrait,
        #[ORM\Column]
        private int $discoveredAt,
        #[ORM\Column]
        private string $moderatorTypes,
    ) {}

    #[Ignore]
    public function getPortrait(): string
    {
        return $this->portrait;
    }

    public function getDiscoveredAt(): int
    {
        return $this->discoveredAt;
    }

    public function getModeratorTypes(): string
    {
        return $this->moderatorTypes;
    }
}
