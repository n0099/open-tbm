<?php

namespace App\Eloquent\Model\Post;

use App\Eloquent\Model\Post\Content\ReplyContentModel;
use App\Eloquent\Model\Post\Content\SubReplyContentModel;
use App\Eloquent\ModelHasProtoBufAttribute;
use App\Eloquent\ModelHasPublicField;
use App\Eloquent\ModelWithTableNameSplitByFid;
use App\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class PostModel extends ModelWithTableNameSplitByFid
{
    use ModelHasPublicField;
    use ModelHasProtoBufAttribute;

    protected const TIMESTAMP_FIELDS = [
        'createdAt',
        'updatedAt',
        'lastSeenAt'
    ];

    private const MODEL_CLASS_TO_TABLE_NAME_SUFFIX = [
        ThreadModel::class => 'thread',
        ReplyModel::class => 'reply',
        ReplyContentModel::class => 'reply_content',
        SubReplyModel::class => 'subReply',
        SubReplyContentModel::class => 'subReply_content'
    ];

    protected function getTableNameSuffix(): string
    {
        return self::MODEL_CLASS_TO_TABLE_NAME_SUFFIX[$this::class];
    }

    protected function getTableNameWithFid(int $fid): string
    {
        return "tbmc_f{$fid}_" . $this->getTableNameSuffix();
    }

    public function scopeSelectCurrentAndParentPostID(Builder $query): Builder
    {
        // only fetch current post ID and its parent posts ID when we can fetch all fields
        // since BaseQuery::fillWithParentPost() will query detailed value of other fields
        return $query->addSelect(array_values(\array_slice(
            Helper::POST_TYPE_TO_ID,
            0,
            array_search($this->getTableNameSuffix(), Helper::POST_TYPES, true) + 1
        )));
    }

    public function scopeTid(Builder $query, Collection|array|int $tid): Builder
    {
        return $this->scopeIDType($query, 'tid', $tid);
    }

    /**
     * @param Builder<PostModel> $query
     * @param string $postIDName
     * @param Collection<array-key, int>|list<int>|int $postID
     * @return Builder<PostModel>
     */
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
}
