<?php

namespace App\Tieba;

class PostFactory
{
    private static function getPostByModels(iterable $models, string $postType): array
    {
        $posts = [];
        $postTypeIdName = [
            Thread::class => 'tid',
            Reply::class => 'pid',
            SubReply::class => 'spid',
        ];

        // could be replaced with convertIDListKey()
        foreach ($models as $model) {
            $posts[$model[$postTypeIdName[$postType]]] = new $postType($model);
        }

        return $posts;
    }

    public static function getThreadsByModels(iterable $models): array
    {
        return self::getPostByModels($models, Thread::class);
    }

    public static function getRepliesByModels(iterable $models): array
    {
        return self::getPostByModels($models, Reply::class);
    }

    public static function getSubRepliesByModels(iterable $models): array
    {
        return self::getPostByModels($models, SubReply::class);
    }
}