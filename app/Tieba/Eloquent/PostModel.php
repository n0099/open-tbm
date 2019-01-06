<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\InsertOnDuplicateKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Post
 * Parent abstract class for App\Tieba\Thread|Reply|SubReply class.
 *
 * @package App\Tieba\Eloquent
 */
abstract class PostModel extends Model
{
    use InsertOnDuplicateKey;

    /**
     * @var string Default table name.
     * @throw SQL:NoSuchTableException
     */
    protected $table = 'tbm_f0';

    /**
     * @var array Let all model attributes fillable.
     */
    protected $guarded = [];

    /**
     * @var int|string Forum id of this post, might be 'me0407'.
     */
    protected $forumID;

    protected $fields;

    protected $hidedFields;

    protected function scopeIDType(Builder $query, string $postIDName, $postID): Builder
    {
        if (is_int($postID)) {
            return $query->where($postIDName, $postID);
        } elseif (is_array($postID)) {
            return $query->whereIn($postIDName, $postID);
        } else {
            throw new \InvalidArgumentException("{$postIDName} must be int or array");
        }
    }

    public function scopeHidePrivateFields(Builder $query): Builder
    {
        return $query->select(array_diff($this->fields, $this->hidedFields));
    }

    abstract public function scopeTid(Builder $query, $tid): Builder;

    abstract public function toPost(): \App\Tieba\Post;

    /**
     * Override construct method for setting valid forum id and table name.
     *
     * @param $forumID
     */
    /*public function __construct($forumId)
    {
        if (is_int($forumId)) {
            $this->forumId = $forumId;
            $postTypeClassNamePlural = [
                'App\Thread' => 'threads',
                'App\Reply' => 'replies',
                'App\SubReply' => 'sub_replies'
            ];
            $test = new Reply(0);
            debug($test::class);
            debug(get_class($test));

            //$this->table = "tbm_f{$forumId}_" . $postTypeClassNamePlural[$this::class];
        }

        parent::__construct([]);
    }*/

    /**
     * Override the parent relation instance method for passing valid forum id to new related model.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function newRelatedInstance($class)
    {
        return tap((new $class())->setForumID($this->forumID), function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Override the parent newInstance method for passing valid forum id to model's query builder.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static((array) $attributes);

        $model->exists = $exists;

        $model->setConnection(
            $this->getConnectionName()
        );

        $model->setForumID($this->forumID);

        return $model;
    }

    /**
     * Setting model table name by forum id and post type.
     *
     * @param int|string $forumID
     *
     * @return PostModel
     */
    public function setForumID($forumID): self
    {
        if (is_int($forumID)) {
            $tableNamePrefix = "tbm_f{$forumID}_";
        } elseif ($forumID == 'me0407') {
            $tableNamePrefix = 'tbm_me0407_';
        } else {
            throw new \InvalidArgumentException;
        }

        $this->forumID = $forumID;

        $postTypeClassNamePlural = [
            ThreadModel::class => 'threads',
            ReplyModel::class => 'replies',
            SubReplyModel::class => 'subReplies'
        ];
        $this->setTable($tableNamePrefix . $postTypeClassNamePlural[get_class($this)]);

        return $this;
    }
}
