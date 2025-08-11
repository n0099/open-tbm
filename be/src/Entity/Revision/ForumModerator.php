<?php

namespace App\Entity\Revision;

use App\Repository\Revision\ForumModeratorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumModeratorRepository::class)]
#[ORM\Table(name: '"tbmcr_forumModerator"')]
class ForumModerator
{
    #[ORM\Column, ORM\Id] public int $discoveredAt;
    #[ORM\Column, ORM\Id] public int $fid;
    #[ORM\Column, ORM\Id] public string $portrait;
    #[ORM\Column, ORM\Id] public string $moderatorTypes;
}
