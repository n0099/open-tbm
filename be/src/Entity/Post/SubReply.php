<?php

namespace App\Entity\Post;

use App\Repository\Post\SubReplyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubReplyRepository::class)]
#[ORM\Table(name: '"tbmc_subReply"')]
class SubReply extends Post
{
    #[ORM\Column(type: 'bigint')] public int $tid;
    #[ORM\Column(type: 'bigint')] public int $pid;
    #[ORM\Column(type: 'bigint'), ORM\Id] public int $spid;
}
