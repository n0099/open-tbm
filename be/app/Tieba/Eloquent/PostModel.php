<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\ModelWithHiddenFields;
use Illuminate\Database\Eloquent\Builder;
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
    protected $table = 'tbm_f0';

    protected int $fid = 0;

    protected const TIMESTAMP_FIELD_NAMES = [
        'createdAt',
        'updatedAt',
        'lastSeen'
    ];

    private const POST_TYPE_CLASS_PLURAL_NAMES = [
        ThreadModel::class => 'threads',
        ReplyModel::class => 'replies',
        ReplyContentModel::class => 'replies_content',
        SubReplyModel::class => 'subReplies',
        SubReplyContentModel::class => 'subReplies_content'
    ];

    /**
     * Setting model table name by fid and post type
     */
    public function setFid(int $fid): static
    {
        $this->fid = $fid;
        $this->setTable("tbm_f{$fid}_" . self::POST_TYPE_CLASS_PLURAL_NAMES[\get_class($this)]);

        return $this;
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
