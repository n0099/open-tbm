<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post\Content;

use App\Entity\BlobResourceGetter;
use Doctrine\ORM\Mapping as ORM;
use TbClient\Wrapper\PostContentWrapper;

#[ORM\MappedSuperclass]
abstract class PostContent
{
    /** @type resource|null */
    #[ORM\Column] private $protoBufBytes;

    public function getProtoBufBytes(): ?\stdClass
    {
        return BlobResourceGetter::protoBuf($this->protoBufBytes, PostContentWrapper::class);
    }
}
