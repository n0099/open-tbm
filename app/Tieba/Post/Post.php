<?php

namespace App\Tieba\Post;

use Illuminate\Database\Eloquent\Model;

abstract class Post
{
    /**
     * Create a post helper with PostModel or array
     *
     * @param array|Model $postData
     */
    public function __construct($postData)
    {
        $initialWith = function ($postProperties) {
            $postIDByType = [
                Thread::class => 'tid',
                Reply::class => 'pid',
                SubReply::class => 'spid'
            ];
            if (! isset($postProperties[$postIDByType[\get_class($this)]])) {
                throw new \DomainException('Initial object doesn\'t match with class type');
            }

            foreach ($postProperties as $postPropertyName => $postPropertyValue) {
                $this->$postPropertyName = $postPropertyValue;
            }
        };

        if ($postData instanceof Model) {
            /*if (count($postData) > 1) {
                throw new \LengthException('Initial collection can\'t larger than one element');
            }*/

            $initialWith($postData->toArray());
        } elseif (\is_array($postData)) {
            $initialWith($postData);
        } else {
            throw new \InvalidArgumentException('Unexpected initial object: ' . \gettype($postData));
        }
    }

    /**
     * Format post content json to html using formatPostJsonContent view
     *
     * @param array $content
     * @return string
     */
    public static function convertJsonContentToHtml(array $content): string
    {
        // remove spamming \n then trim spaces due to blade @break directive
        return str_replace("\n", null, trim(view('formatPostJsonContent', ['content' => $content])));
    }
}
