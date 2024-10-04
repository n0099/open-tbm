<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use TbClient\UserDeps\Icon;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '"tbmc_user"')]
class User extends TimestampedEntity
{
    #[ORM\Column, ORM\Id] private int $uid;
    #[ORM\Column] private ?string $name;
    /** @type resource|null */
    #[ORM\Column] private $displayName;
    #[ORM\Column] private string $portrait;
    #[ORM\Column] private ?int $portraitUpdatedAt;
    #[ORM\Column] private ?int $gender;
    #[ORM\Column] private ?string $fansNickname;
    /** @type resource|null */
    #[ORM\Column] private $icon;
    #[ORM\Column] private string $ipGeolocation;

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDisplayName(): ?string
    {
        return BlobResourceGetter::resource($this->displayName);
    }

    public function getPortrait(): string
    {
        return $this->portrait;
    }

    public function getPortraitUpdatedAt(): ?int
    {
        return $this->portraitUpdatedAt;
    }

    public function getGender(): ?int
    {
        return $this->gender;
    }

    public function getFansNickname(): ?string
    {
        return $this->fansNickname;
    }

    public function getIcon(): ?\stdClass
    {
        return BlobResourceGetter::protoBuf($this->icon, Icon::class);
    }

    public function getIpGeolocation(): string
    {
        return $this->ipGeolocation;
    }
}
