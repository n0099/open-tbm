<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Entity\Post\Content;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class PostContent
{
    #[ORM\Column] private ?string $protoBufBytes;

    public function getProtoBufBytes(): ?string
    {
        return $this->protoBufBytes;
    }
}
