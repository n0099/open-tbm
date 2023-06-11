<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use TbClient\Post\Common\Lbs;

class ReplyModel extends PostModel
{
    protected $primaryKey = 'pid';

    protected static array $fields = [
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

    protected $casts = [
        'subReplyCount' => NullableNumericAttributeCast::class,
        'isFold' => NullableBooleanAttributeCast::class,
        'agreeCount' => NullableNumericAttributeCast::class,
        'disagreeCount' => NullableNumericAttributeCast::class
    ];

    protected function geolocation(): Attribute
    {
        return self::makeProtoBufAttribute(Lbs::class);
    }

    /**
     * @psalm-return BelongsTo<ThreadModel>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    /**
     * @psalm-return HasMany<SubReplyModel>
     */
    public function subReplies(): HasMany
    {
        return $this->hasMany(SubReplyModel::class, 'pid', 'pid');
    }

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }
}
