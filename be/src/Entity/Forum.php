<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ORM\Table(name: '"tbm_forum"')]
class Forum
{
    #[ORM\Column, ORM\Id] public int $fid;
    #[ORM\Column] public string $name;
    #[ORM\Column] public bool $isCrawling;
}
