<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

abstract class TimestampedEntity
{
    #[ORM\Column] private int $createdAt;
    #[ORM\Column] private ?int $updatedAt;

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?int
    {
        return $this->updatedAt;
    }
}
