<?php

namespace App\Entity\Post;

use App\Entity\BlobResourceGetter;
use App\Repository\Post\ThreadRepository;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Post\Common\Lbs;
use TbClient\Post\Common\Zan;

#[ORM\Entity(repositoryClass: ThreadRepository::class)]
#[ORM\Table(name: '"tbmc_thread"')]
class Thread extends Post
{
    #[ORM\Column(type: 'bigint'), ORM\Id] public int $tid;
    #[ORM\Column(type: 'bigint')] public int $threadType;
    #[ORM\Column] public ?string $stickyType;
    #[ORM\Column] public ?string $topicType;
    #[ORM\Column] public ?int $isGood;
    #[ORM\Column] public string $title;
    #[ORM\Column] public int $latestReplyPostedAt;
    #[ORM\Column] public ?int $latestReplierId;
    #[ORM\Column] public ?int $replyCount;
    #[ORM\Column] public ?int $viewCount;
    #[ORM\Column] public ?int $shareCount;
    /** @var ?resource */
    #[ORM\Column] public $zan;
    /** @var ?resource */
    #[ORM\Column] public $geolocation;
    #[ORM\Column] public ?string $authorPhoneType;

    public function getZan(): ?array
    {
        return BlobResourceGetter::protoBuf($this->zan, Zan::class);
    }

    public function getGeolocation(): ?array
    {
        return BlobResourceGetter::protoBuf($this->geolocation, Lbs::class);
    }
}
