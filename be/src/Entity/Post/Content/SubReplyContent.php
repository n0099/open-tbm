<?php

namespace App\Entity\Post\Content;

use App\Repository\Post\Content\SubReplyContentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubReplyContentRepository::class)]
#[ORM\Table(name: '"tbmc_subReply_content"')]
class SubReplyContent extends PostContent
{
    #[ORM\Column(type: 'bigint'), ORM\Id] private int $spid;

    public function getSpid(): int
    {
        return $this->spid;
    }

    public function setSpid(int $value): self
    {
        $this->spid = $value;
        return $this;
    }
}
