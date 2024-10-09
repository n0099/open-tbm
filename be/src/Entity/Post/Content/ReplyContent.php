<?php

/** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post\Content;

use App\Repository\Post\Content\ReplyContentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReplyContentRepository::class)]
class ReplyContent extends PostContent
{
    #[ORM\Column, ORM\Id] private int $pid;

    public function getPid(): int
    {
        return $this->pid;
    }
}
