<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\ModelHelper;
use App\Tieba\Post\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Class Post
 * Parent abstract class for App\Tieba\Post\Thread|Reply|SubReply class
 *
 * @package App\Tieba\Eloquent
 */
abstract class PostModel extends Model
{
    use ModelHelper;

    /**
     * @var string Default table name
     * @throw SQL:NoSuchTableException
     */
    protected $table = 'tbm_f0';

    /**
     * @var array Let all model attributes assignable
     */
    protected $guarded = [];

    protected int $fid = 0;

    protected array $fields;

    protected array $hidedFields;

    abstract public function toPost(): Post;

    /**
     * Setting model table name by forum id and post type
     */
    public function setFid(int $fid): static
    {
        $this->fid = $fid;

        $postTypeClassNamePlural = [
            ThreadModel::class => 'threads',
            ReplyModel::class => 'replies',
            SubReplyModel::class => 'subReplies'
        ];
        $this->setTable("tbm_f{$fid}_" . $postTypeClassNamePlural[\get_class($this)]);

        return $this;
    }

    public function scopeTid(Builder $query, $tid): Builder
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
        throw new \InvalidArgumentException("{$postIDName} must be int or array");
    }

    /**
     * Override the parent relation instance method for passing valid forum id to new related post model
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
     * Override the parent newInstance method for passing valid forum id to model's query builder
     */
    public function newInstance($attributes = [], $exists = false): static
    {
        return parent::newInstance($attributes, $exists)->setFid($this->fid);
    }
}
