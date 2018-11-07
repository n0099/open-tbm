<?php

namespace App\Tieba;

class PostFactory
{
    private static function getPostByModels(iterable $models, string $postType)
    {
        $posts = [];
        $postTypeIdName = [
            Post::class => 'tid',
            Reply::class => 'pid',
            SubReply::class => 'spid',
        ];
        foreach ($models as $model) {
            $posts[$model[$postTypeIdName[$postType]]] = new $postType($model);
        }
        return $posts;
    }

    public static function getThreadsByModels(iterable $models)
    {
        return self::getPostByModels($models, Thread::class);
    }

    public static function getRepliesByModels(iterable $models)
    {
        return self::getPostByModels($models, Reply::class);
    }

    public static function getSubRepliesByModels(iterable $models)
    {
        return self::getPostByModels($models, SubReply::class);
    }
}