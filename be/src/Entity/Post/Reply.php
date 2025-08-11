<?php

namespace App\Entity\Post;

use App\Entity\BlobResourceGetter;
use App\Repository\Post\ReplyRepository;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Post\Common\Lbs;

#[ORM\Entity(repositoryClass: ReplyRepository::class)]
#[ORM\Table(name: '"tbmc_reply"')]
class Reply extends Post
{
    #[ORM\Column(type: 'bigint')] public int $tid;
    #[ORM\Column(type: 'bigint'), ORM\Id] public int $pid;
    #[ORM\Column] public int $floor;
    #[ORM\Column] public ?int $subReplyCount { get => $this->subReplyCount ?? 0; }
    #[ORM\Column] public ?int $isFold;
    /** @var ?resource */
    #[ORM\Column] public $geolocation {
        get => BlobResourceGetter::protoBuf($this->geolocation, Lbs::class);
    }
    #[ORM\Column] public ?int $signatureId;
}
