<?php

namespace App\Eloquent\Model\Post;

use App\Eloquent\NullableBooleanAttributeCast;
use App\Eloquent\NullableNumericAttributeCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use TbClient\Post\Common\Lbs;
use TbClient\Post\Common\Zan;

/**
 * Model for every Tieba thread post
 *
 * @package App\Tieba\Eloquent
 */
class Thread extends Post
{
    protected $primaryKey = 'tid';

    protected $casts = [
        'isGood' => NullableBooleanAttributeCast::class,
        'replyCount' => NullableNumericAttributeCast::class,
        'viewCount' => NullableNumericAttributeCast::class,
        'shareCount' => NullableNumericAttributeCast::class,
        'agreeCount' => NullableNumericAttributeCast::class,
        'disagreeCount' => NullableNumericAttributeCast::class
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = [
            'tid',
            'threadType',
            'stickyType',
            'topicType',
            'isGood',
            'title',
            'authorUid',
            'postedAt',
            'latestReplyPostedAt',
            'latestReplierUid',
            'replyCount',
            'viewCount',
            'shareCount',
            'agreeCount',
            'disagreeCount',
            'zan',
            'geolocation',
            'authorPhoneType',
            ...parent::TIMESTAMP_FIELDS
        ];
    }

    protected function zan(): Attribute
    {
        return self::makeProtoBufAttribute(Zan::class);
    }

    protected function geolocation(): Attribute
    {
        return self::makeProtoBufAttribute(Lbs::class);
    }

    /**
     * @psalm-return HasMany<Reply>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class, 'tid', 'tid');
    }
}
