<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity;

use App\DTO\User\AuthorExpGrade;
use App\DTO\User\ForumModerator;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use TbClient\UserDeps\Icon;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '"tbmc_user"')]
class User extends TimestampedEntity
{
    #[ORM\Column, ORM\Id] private int $uid;
    #[ORM\Column] private ?string $name;
    /** @type ?resource */
    #[ORM\Column] private $displayName;
    #[ORM\Column] private string $portrait;
    #[ORM\Column] private ?int $portraitUpdatedAt;
    #[ORM\Column] private ?int $gender;
    #[ORM\Column] private ?string $fansNickname;
    /** @type ?resource */
    #[ORM\Column] private $icon;
    #[ORM\Column] private string $ipGeolocation;
    private ?ForumModerator $currentForumModerator;
    private ?AuthorExpGrade $currentAuthorExpGrade;

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

    public function getIcon(): ?Icon
    {
        return BlobResourceGetter::protoBuf($this->icon, Icon::class);
    }

    public function getIpGeolocation(): string
    {
        return $this->ipGeolocation;
    }

    public function getCurrentForumModerator(): ?ForumModerator
    {
        return $this->currentForumModerator;
    }

    public function setCurrentForumModerator(?ForumModerator $currentForumModerator): void
    {
        $this->currentForumModerator = $currentForumModerator;
    }

    public function getCurrentAuthorExpGrade(): ?AuthorExpGrade
    {
        return $this->currentAuthorExpGrade;
    }

    public function setCurrentAuthorExpGrade(?AuthorExpGrade $currentAuthorExpGrade): void
    {
        $this->currentAuthorExpGrade = $currentAuthorExpGrade;
    }
}
