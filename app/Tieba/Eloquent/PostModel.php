<?php

namespace App\Tieba\Eloquent;

use App\Eloquent\InsertOnDuplicateKey;
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
    protected $forumId;

    protected function scopeIDType($query, string $postIDName, $postID): \Illuminate\Database\Eloquent\Builder
    {
        if (is_int($postID)) {
            return $query->where($postIDName, $postID);
        } elseif (is_array($postID)) {
            return $query->whereIn($postIDName, $postID);
        } else {
            throw new \InvalidArgumentException("{$postIDName} must be int or array");
        }
    }

    abstract public function scopeTid($query, $tid): \Illuminate\Database\Eloquent\Builder;

    abstract public function toPost(): \App\Tieba\Post;

    /**
     * Override construct method for setting valid forumId and table name.
     *
     * @param $forumId
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
     * Override the parent relation instance method for passing valid forumId to new related model.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function newRelatedInstance($class)
    {
        return tap((new $class())->setForumId($this->forumId), function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Override the parent newInstance method for passing valid forumId to model's query builder.
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

        $model->setForumId($this->forumId);

        return $model;
    }

    /**
     * Setting model table name by forum id and post type.
     *
     * @param int|string $forumId
     *
     * @return PostModel
     */
    public function setForumId($forumId): self
    {
        if (is_int($forumId)) {
            $tableNamePrefix = "tbm_f{$forumId}_";
        } elseif ($forumId == 'me0407') {
            $tableNamePrefix = 'tbm_me0407_';
        } else {
            throw new \InvalidArgumentException;
        }

        $this->forumId = $forumId;

        $postTypeClassNamePlural = [
            ThreadModel::class => 'threads',
            ReplyModel::class => 'replies',
            SubReplyModel::class => 'subReplies'
        ];
        $this->setTable($tableNamePrefix . $postTypeClassNamePlural[get_class($this)]);

        return $this;
    }
}
