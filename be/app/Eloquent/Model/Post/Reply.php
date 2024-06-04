<?php

namespace App\Eloquent\Model\Post;

use App\Eloquent\ModelAttributeMaker;
use App\Eloquent\NullableNumericAttributeCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use TbClient\Post\Common\Lbs;

class Reply extends Post
{
    protected $primaryKey = 'pid';

    protected $casts = [
        'subReplyCount' => NullableNumericAttributeCast::class,
        'agreeCount' => NullableNumericAttributeCast::class,
        'disagreeCount' => NullableNumericAttributeCast::class
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = [
            'tid',
            'pid',
            'floor',
            'authorUid',
            'subReplyCount',
            'postedAt',
            'isFold',
            'agreeCount',
            'disagreeCount',
            'geolocation',
            'signatureId',
            ...parent::TIMESTAMP_FIELDS
        ];
    }

    protected function geolocation(): Attribute
    {
        return ModelAttributeMaker::makeProtoBufAttribute(Lbs::class);
    }

    /**
     * @psalm-return BelongsTo<Thread>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'tid', 'tid');
    }

    /**
     * @psalm-return HasMany<SubReply>
     */
    public function subReplies(): HasMany
    {
        return $this->hasMany(SubReply::class, 'pid', 'pid');
    }

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }
}
