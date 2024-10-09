<?php

namespace App\Entity\Post;

abstract class PostWithContent extends Post
{
    private ?array $content;

    public function getContent(): ?array
    {
        return $this->content;
    }

    public function setContent(?array $content): void
    {
        $this->content = $content;
    }
}
