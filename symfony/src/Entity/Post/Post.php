<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post;

use App\Entity\TimestampedEntity;
use Doctrine\ORM\Mapping as ORM;

abstract class Post extends TimestampedEntity
{
    #[ORM\Column] private int $tid;
    #[ORM\Column] private int $authorUid;
    #[ORM\Column] private ?int $lastSeenAt;

    public function getTid(): int
    {
        return $this->tid;
    }

    public function getAuthorUid(): int
    {
        return $this->authorUid;
    }

    public function getLastSeenAt(): ?int
    {
        return $this->lastSeenAt;
    }
}
