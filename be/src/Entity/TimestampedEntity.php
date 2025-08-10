<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class TimestampedEntity
{
    #[ORM\Column] public int $createdAt;
    #[ORM\Column] public ?int $updatedAt;
}
