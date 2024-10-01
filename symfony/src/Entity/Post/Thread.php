<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post;

use App\Repository\Post\ThreadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThreadRepository::class)]
class Thread extends Post
{
    #[ORM\Id, ORM\Column] private int $tid;
    #[ORM\Column] private int $threadType;
    #[ORM\Column] private ?string $stickyType;
    #[ORM\Column] private ?string $topicType;
    #[ORM\Column] private ?int $isGood;
    #[ORM\Column] private string $title;
    #[ORM\Column] private int $postedAt;
    #[ORM\Column] private int $latestReplyPostedAt;
    #[ORM\Column] private ?int $replyCount;
    #[ORM\Column] private ?int $viewCount;
    #[ORM\Column] private ?int $shareCount;
    #[ORM\Column] private ?int $agreeCount;
    #[ORM\Column] private ?int $disagreeCount;
    #[ORM\Column] private ?string $zan;
    #[ORM\Column] private ?string $geolocation;
    #[ORM\Column] private ?string $authorPhoneType;

    public function getTid(): int
    {
        return $this->tid;
    }

    public function getThreadType(): int
    {
        return $this->threadType;
    }

    public function getStickyType(): ?string
    {
        return $this->stickyType;
    }

    public function getTopicType(): ?string
    {
        return $this->topicType;
    }

    public function getIsGood(): ?int
    {
        return $this->isGood;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPostedAt(): int
    {
        return $this->postedAt;
    }

    public function getLatestReplyPostedAt(): int
    {
        return $this->latestReplyPostedAt;
    }

    public function getReplyCount(): ?int
    {
        return $this->replyCount;
    }

    public function getViewCount(): ?int
    {
        return $this->viewCount;
    }

    public function getShareCount(): ?int
    {
        return $this->shareCount;
    }

    public function getAgreeCount(): ?int
    {
        return $this->agreeCount;
    }

    public function getDisagreeCount(): ?int
    {
        return $this->disagreeCount;
    }

    public function getZan(): ?string
    {
        return $this->zan;
    }

    public function getGeolocation(): ?string
    {
        return $this->geolocation;
    }

    public function getAuthorPhoneType(): ?string
    {
        return $this->authorPhoneType;
    }
}
