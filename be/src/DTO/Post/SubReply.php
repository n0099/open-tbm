<?php

namespace App\DTO\Post;

use App\Entity\Post\SubReply as SubReplyEntity;
use Symfony\Component\Serializer\Attribute\Ignore;

class SubReply extends SubReplyEntity implements SortablePost
{
    use Post { fromEntity as private fromPostEntity; }
    use PostWithContent;

    #[Ignore] public int $fid;
    #[Ignore] public bool $isMatchQuery { get => true; set {} }

    public static function fromEntity(SubReplyEntity $entity): self
    {
        $dto = self::fromPostEntity($entity);
        $dto->tid = $entity->tid;
        $dto->pid = $entity->pid;
        $dto->spid = $entity->spid;
        return $dto;
    }
}
