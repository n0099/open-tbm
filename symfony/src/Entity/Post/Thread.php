<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post;

use App\Entity\BlobResourceGetter;
use App\Repository\Post\ThreadRepository;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Post\Common\Lbs;
use TbClient\Post\Common\Zan;

#[ORM\Entity(repositoryClass: ThreadRepository::class)]
class Thread extends Post
{
    #[ORM\Column, ORM\Id] private int $tid;
    #[ORM\Column] private int $threadType;
    #[ORM\Column] private ?string $stickyType;
    #[ORM\Column] private ?string $topicType;
    #[ORM\Column] private ?int $isGood;
    #[ORM\Column] private string $title;
    #[ORM\Column] private int $latestReplyPostedAt;
    #[ORM\Column] private ?int $replyCount;
    #[ORM\Column] private ?int $viewCount;
    #[ORM\Column] private ?int $shareCount;
    /** @type resource|null */
    #[ORM\Column] private $zan;
    /** @type resource|null */
    #[ORM\Column] private $geolocation;
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

    public function getZan(): ?\stdClass
    {
        return BlobResourceGetter::protoBuf($this->zan, Zan::class);
    }

    public function getGeolocation(): ?\stdClass
    {
        return BlobResourceGetter::protoBuf($this->geolocation, Lbs::class);
    }

    public function getAuthorPhoneType(): ?string
    {
        return $this->authorPhoneType;
    }
}
