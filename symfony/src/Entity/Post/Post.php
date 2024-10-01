<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post;

use App\Entity\TimestampedEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class Post extends TimestampedEntity
{
    #[ORM\Column] private int $authorUid;
    #[ORM\Column] private int $postedAt;
    #[ORM\Column] private ?int $lastSeenAt;
    #[ORM\Column] private ?int $agreeCount;
    #[ORM\Column] private ?int $disagreeCount;

    public function getAuthorUid(): int
    {
        return $this->authorUid;
    }

    public function getPostedAt(): int
    {
        return $this->postedAt;
    }

    public function getLastSeenAt(): ?int
    {
        return $this->lastSeenAt;
    }

    public function getAgreeCount(): ?int
    {
        return $this->agreeCount;
    }

    public function getDisagreeCount(): ?int
    {
        return $this->disagreeCount;
    }
}
