<?php

namespace App\Entity\Post;

use App\Repository\Post\SubReplyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubReplyRepository::class)]
#[ORM\Table(name: '"tbmc_subReply"')]
class SubReply extends Post
{
    #[ORM\Column(type: 'bigint')] protected int $tid;
    #[ORM\Column(type: 'bigint')] protected int $pid;
    #[ORM\Column(type: 'bigint'), ORM\Id] protected int $spid;

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $value): self
    {
        $this->pid = $value;
        return $this;
    }

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
