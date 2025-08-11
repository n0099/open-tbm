<?php

namespace App\Entity\Post;

use App\Entity\TimestampedEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class Post extends TimestampedEntity
{
    #[ORM\Column] public int $fid;
    public int $tid;
    #[ORM\Column] public int $authorUid;
    #[ORM\Column] public int $postedAt;
    #[ORM\Column] public ?int $lastSeenAt;
    #[ORM\Column] public ?int $agreeCount { get => $this->agreeCount ?? 0; }
    #[ORM\Column] public ?int $disagreeCount { get => $this->disagreeCount ?? 0; }
}
