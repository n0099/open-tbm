<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Revision;

use App\Repository\Revision\ForumModeratorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumModeratorRepository::class)]
#[ORM\Table(name: '"tbmcr_forumModerator"')]
class ForumModerator
{
    #[ORM\Column, ORM\Id] private int $discoveredAt;
    #[ORM\Column, ORM\Id] private int $fid;
    #[ORM\Column, ORM\Id] private string $portrait;
    #[ORM\Column, ORM\Id] private string $moderatorTypes;

    public function getDiscoveredAt(): int
    {
        return $this->discoveredAt;
    }

    public function getFid(): int
    {
        return $this->fid;
    }

    public function getPortrait(): string
    {
        return $this->portrait;
    }

    public function getModeratorTypes(): string
    {
        return $this->moderatorTypes;
    }
}
