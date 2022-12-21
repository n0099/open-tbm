<?php

namespace App\Tieba\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'authorManagerType',
        'authorExpGrade',
        'subReplyCount',
        'postTime',
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

    public function thread(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function subReplies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubReplyModel::class, 'pid', 'pid');
    }

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }
}
