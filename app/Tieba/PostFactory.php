<?php

namespace App\Tieba;

class PostFactory
{
    private static function getPostByModels(iterable $models, string $postType): array
    {
        $posts = [];
        $postTypeIDName = [
            Thread::class => 'tid',
            Reply::class => 'pid',
            SubReply::class => 'spid',
        ];

        // could be replaced with convertIDListKey()
        foreach ($models as $model) {
            $posts[$model[$postTypeIDName[$postType]]] = new $postType($model);
        }

        return $posts;
    }

    public static function getThreadsByModels(iterable $models): array
    {
        return static::getPostByModels($models, Thread::class);
    }

    public static function getRepliesByModels(iterable $models): array
    {
        return static::getPostByModels($models, Reply::class);
    }

    public static function getSubRepliesByModels(iterable $models): array
    {
        return static::getPostByModels($models, SubReply::class);
    }
}