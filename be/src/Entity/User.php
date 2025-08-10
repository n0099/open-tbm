<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Wrapper\UserIconWrapper;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '"tbmc_user"')]
class User extends TimestampedEntity
{
    #[ORM\Column, ORM\Id] public int $uid;
    #[ORM\Column] public ?string $name;
    /** @var ?resource */
    #[ORM\Column] public $displayName {
        get => BlobResourceGetter::resource($this->displayName);
    }
    #[ORM\Column] public string $portrait;
    #[ORM\Column] public ?int $portraitUpdatedAt;
    #[ORM\Column] public ?int $gender;
    #[ORM\Column] public ?string $fansNickname;
    /** @var ?resource */
    #[ORM\Column] public $icon {
        get => BlobResourceGetter::protoBufWrapper($this->icon, UserIconWrapper::class);
    }
    #[ORM\Column] public ?string $ipGeolocation;

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getPortrait(): string
    {
        return $this->portrait;
    }
}
