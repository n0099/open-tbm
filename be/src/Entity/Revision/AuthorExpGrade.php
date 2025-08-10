<?php

namespace App\Entity\Revision;

use App\Repository\Revision\AuthorExpGradeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorExpGradeRepository::class)]
#[ORM\Table(name: '"tbmcr_authorExpGrade"')]
class AuthorExpGrade
{
    #[ORM\Column, ORM\Id] public int $discoveredAt;
    #[ORM\Column, ORM\Id] public int $fid;
    #[ORM\Column, ORM\Id] public int $uid;
    #[ORM\Column] public string $triggeredBy;
    #[ORM\Column] public int $authorExpGrade;
}
