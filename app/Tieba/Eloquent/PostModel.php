<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\InsertOnDuplicateKey;
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
    use InsertOnDuplicateKey;
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

    /**
     * @var int Forum id of this post
     */
    protected $fid;

    protected $fields;

    protected $hidedFields;

    public $updateExpectFields;

    protected function scopeIDType(Builder $query, string $postIDName, $postID): Builder
    {
        if (\is_int($postID)) {
            return $query->where($postIDName, $postID);
        }
        if (\is_array($postID) || $postID instanceof Collection) {
            return $query->whereIn($postIDName, $postID);
        }
        throw new \InvalidArgumentException("{$postIDName} must be int or array");
    }

    public function scopeHidePrivateFields(Builder $query): Builder
    {
        return $query->select(array_diff($this->fields, $this->hidedFields));
    }

    abstract public function scopeTid(Builder $query, $tid): Builder;

    abstract public function toPost(): Post;

    /**
     * Override the parent relation instance method for passing valid forum id to new related post model
     *
     * @param  string  $class
     * @return mixed
     */
    protected function newRelatedInstance($class)
    {
        return tap((new $class())->setFid($this->fid), function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Override the parent newInstance method for passing valid forum id to model's query builder
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        return parent::newInstance(...\func_get_args())->setFid($this->fid);
    }

    /**
     * Setting model table name by forum id and post type
     *
     * @param int $fid
     *
     * @return PostModel
     */
    public function setFid(int $fid): self
    {
        $this->fid = $fid;

        $tableNamePrefix = "tbm_f{$fid}_";
        $postTypeClassNamePlural = [
            ThreadModel::class => 'threads',
            ReplyModel::class => 'replies',
            SubReplyModel::class => 'subReplies'
        ];
        $this->setTable($tableNamePrefix . $postTypeClassNamePlural[\get_class($this)]);

        return $this;
    }
}
