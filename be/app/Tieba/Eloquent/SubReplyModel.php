<?php

namespace App\Tieba\Eloquent;

use App\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class SubReplyModel extends PostModel
{
    protected $primaryKey = 'spid';

    protected static array $fields = [
        ...Helper::POST_ID,
        'authorUid',
        'authorManagerType',
        'authorExpGrade',
        'postTime',
        'agreeCount',
        'disagreeCount',
        ...parent::TIMESTAMP_FIELDS
    ];

    protected $casts = [
        'agreeCount' => NullableNumericAttributeCast::class,
        'disagreeCount' => NullableNumericAttributeCast::class
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ThreadModel::class, 'tid', 'tid');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(ReplyModel::class, 'pid', 'pid');
    }

    public function scopePid(Builder $query, Collection|array|int $pid): Builder
    {
        return $this->scopeIDType($query, 'pid', $pid);
    }

    public function scopeSpid(Builder $query, Collection|array|int $spid): Builder
    {
        return $this->scopeIDType($query, 'spid', $spid);
    }
}
