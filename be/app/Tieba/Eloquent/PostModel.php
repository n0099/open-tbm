<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\ModelWithHiddenFields;
use App\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;

/**
 * Class Post
 * Parent abstract class for App\Tieba\Post\Thread|Reply|SubReply class
 *
 * @package App\Tieba\Eloquent
 */
abstract class PostModel extends ModelWithHiddenFields
{
    /**
     * @var string Default table name
     * @throw SQL:NoSuchTableException
     */
    protected $table = 'tbmc_f0';

    protected int $fid = 0;

    protected const TIMESTAMP_FIELDS = [
        'createdAt',
        'updatedAt',
        'lastSeen'
    ];

    private const MODEL_CLASS_TO_TABLE_NAME_SUFFIX = [
        ThreadModel::class => 'thread',
        ReplyModel::class => 'reply',
        ReplyContentModel::class => 'reply_content',
        SubReplyModel::class => 'subReply',
        SubReplyContentModel::class => 'subReply_content'
    ];

    /**
     * Setting model table name by fid and post type
     */
    public function setFid(int $fid): static
    {
        $this->fid = $fid;
        $this->setTable("tbmc_f{$fid}_" . self::MODEL_CLASS_TO_TABLE_NAME_SUFFIX[$this::class]);

        return $this;
    }

    public function scopeSelectCurrentAndParentPostID(Builder $query): Builder
    {
        // only fetch current post ID and its parent posts ID when we can fetch all fields
        // since BaseQuery::fillWithParentPost() will query detailed value of other fields
        return $query->addSelect(array_values(\array_slice(
            Helper::POST_TYPE_TO_ID,
            0,
            array_search(self::MODEL_CLASS_TO_TABLE_NAME_SUFFIX[$this::class], Helper::POST_TYPES) + 1
        )));
    }

    public function scopeTid(Builder $query, Collection|array|int $tid): Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
    }

    protected function scopeIDType(Builder $query, string $postIDName, Collection|array|int $postID): Builder
    {
        if (\is_int($postID)) {
            return $query->where($postIDName, $postID);
        }
        if (\is_array($postID) || $postID instanceof Collection) {
            return $query->whereIn($postIDName, $postID);
        }
        throw new \InvalidArgumentException("$postIDName must be int or array");
    }

    /**
     * @param class-string $protoBufClass
     * @return Attribute
     */
    protected static function makeProtoBufAttribute(string $protoBufClass)
    {
        return Attribute::make(get: static function (?string $value) use ($protoBufClass) {
            if ($value === null) {
                return null;
            }
            $proto = new $protoBufClass();
            $proto->mergeFromString($value);
            return json_decode($proto->serializeToJsonString());
        });
    }

    /**
     * Override the parent relation instance method for passing valid fid to new related post model
     */
    protected function newRelatedInstance($class)
    {
        return tap((new $class())->setFid($this->fid), function ($instance) {
            if (!$instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Override the parent newInstance method for passing valid fid to model's query builder
     */
    public function newInstance($attributes = [], $exists = false): static
    {
        return parent::newInstance($attributes, $exists)->setFid($this->fid);
    }
}
