<?php

namespace App\Entity\Post\Content;

use App\Entity\BlobResourceGetter;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Wrapper\PostContentWrapper;

#[ORM\MappedSuperclass]
abstract class PostContent
{
    /** @var ?resource */
    #[ORM\Column] public $protoBufBytes;
    public ?array $content {
        get => BlobResourceGetter::protoBufWrapper($this->protoBufBytes, PostContentWrapper::class);
    }
}
