<?php

namespace App\Eloquent\Model;

use App\Eloquent\Model\Revision\AuthorExpGrade;
use App\Eloquent\Model\Revision\ForumModerator;
use App\Eloquent\ModelAttributeMaker;
use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use TbClient\Wrapper\UserIconWrapper;

class User extends Model
{
    use ModelHasPublicField;

    public static $snakeAttributes = false; // for relationship attributes

    protected $table = 'tbmc_user';

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
            'updatedAt',
        ];
    }

    protected function displayName(): Attribute
    {
        return ModelAttributeMaker::makeResourceAttribute();
    }

    protected function icon(): Attribute
    {
        return ModelAttributeMaker::makeProtoBufAttribute(UserIconWrapper::class);
    }

    /**
     * @param Builder<User> $query
     * @param Collection<array-key, int>|list<int>|int $uid
     * @return Builder<User>
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
            ->hasOne(ForumModerator::class, 'portrait', 'portrait')
            ->orderBy('discoveredAt', 'DESC')->selectPublicFields();
    }

    public function currentAuthorExpGrade(): HasOne
    {
        return $this // https://laracasts.com/discuss/channels/eloquent/eager-loading-constraints-with-limit-clauses
            ->hasOne(AuthorExpGrade::class, 'uid', 'uid')
            ->orderBy('discoveredAt', 'DESC')->selectPublicFields();
    }
}
