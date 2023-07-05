<?php

namespace App\Eloquent\Model;

use App\Eloquent\Model\Revision\ForumModeratorModel;
use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class UserModel extends Model
{
    use ModelHasPublicField;

    public static $snakeAttributes = false; // for relationship attributes

    protected $table = 'tbmc_user';

    protected $casts = [
        'icon' => 'array'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = [
            'uid',
            'name',
            'displayName',
            'portrait',
            'portraitUpdatedAt',
            'gender',
            'fansNickname',
            'icon',
            'ipGeolocation',
            'createdAt',
            'updatedAt'
        ];
    }

    /**
     * @param Builder<UserModel> $query
     * @param Collection<array-key, int>|list<int>|int $uid
     * @return Builder<UserModel>
     */
    public function scopeUid(Builder $query, Collection|array|int $uid): Builder
    {
        if (\is_int($uid)) {
            return $query->where('uid', $uid);
        }
        if (\is_array($uid) || $uid instanceof Collection) {
            return $query->whereIn('uid', $uid);
        }
        throw new \InvalidArgumentException('Uid must be int or array');
    }

    public function currentForumModerator(): HasOne
    {
        return $this // https://laracasts.com/discuss/channels/eloquent/eager-loading-constraints-with-limit-clauses
            ->hasOne(ForumModeratorModel::class, 'portrait', 'portrait')
            ->orderBy('discoveredAt', 'DESC')->selectPublicFields();
    }
}
