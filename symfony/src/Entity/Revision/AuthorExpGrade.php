<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Revision;

use App\Repository\Revision\AuthorExpGradeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorExpGradeRepository::class)]
#[ORM\Table(name: '"tbmcr_authorExpGrade"')]
class AuthorExpGrade
{
    #[ORM\Column, ORM\Id] private int $discoveredAt;
    #[ORM\Column, ORM\Id] private int $fid;
    #[ORM\Column, ORM\Id] private int $uid;
    #[ORM\Column] private string $triggeredBy;
    #[ORM\Column] private int $authorExpGrade;

    public function getDiscoveredAt(): int
    {
        return $this->discoveredAt;
    }

    public function getFid(): int
    {
        return $this->fid;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getTriggeredBy(): string
    {
        return $this->triggeredBy;
    }

    public function getAuthorExpGrade(): int
    {
        return $this->authorExpGrade;
    }
}
