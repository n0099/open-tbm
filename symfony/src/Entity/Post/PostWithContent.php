<?php

namespace App\Entity\Post;

use TbClient\Wrapper\PostContentWrapper;

abstract class PostWithContent extends Post
{
    private PostContentWrapper $content;

    public function getContent(): PostContentWrapper
    {
        return $this->content;
    }

    public function setContent(PostContentWrapper $content): void
    {
        $this->content = $content;
    }
}
