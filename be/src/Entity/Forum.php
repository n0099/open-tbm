<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ORM\Table(name: '"tbm_forum"')]
class Forum
{
    #[ORM\Column, ORM\Id] private int $fid;
    #[ORM\Column] private string $name;
    #[ORM\Column] private bool $isCrawling;

    public function getFid(): int
    {
        return $this->fid;
    }

    public function setFid(int $fid): void
    {
        $this->fid = $fid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isCrawling(): bool
    {
        return $this->isCrawling;
    }

    public function setIsCrawling(bool $isCrawling): void
    {
        $this->isCrawling = $isCrawling;
    }
}
