<?php

namespace App\Entity\Post\Content;

use App\Repository\Post\Content\ReplyContentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReplyContentRepository::class)]
#[ORM\Table(name: '"tbmc_reply_content"')]
class ReplyContent extends PostContent
{
    #[ORM\Column(type: 'bigint'), ORM\Id] private int $pid;

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $value): self
    {
        $this->pid = $value;
        return $this;
    }
}
