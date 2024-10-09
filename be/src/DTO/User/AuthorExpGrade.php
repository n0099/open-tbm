<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\DTO\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
readonly class AuthorExpGrade
{
    public function __construct(
        #[ORM\Column, ORM\Id] private int $uid,
        #[ORM\Column] private int $discoveredAt,
        #[ORM\Column] private int $authorExpGrade)
    {}

    #[Ignore]
    public function getUid(): int
    {
        return $this->uid;
    }

    public function getDiscoveredAt(): int
    {
        return $this->discoveredAt;
    }

    public function getAuthorExpGrade(): int
    {
        return $this->authorExpGrade;
    }
}
