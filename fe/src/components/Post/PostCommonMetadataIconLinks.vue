<template>
    <a :href="tiebaPostLink(post.tid, postTypeID === 'tid' ? undefined : postIDSelector())"
       target="_blank" class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="link" size="lg" class="align-bottom" />
    </a>
    <a :data-tippy-content="`<h6>${postTypeID}：${postIDSelector()}</h6><hr />
        首次收录时间：${formatTime(post.createdAt)}<br />
        最后更新时间：${formatTime(post.updatedAt)}<br />
        最后发现时间：${formatTime(post.lastSeenAt)}`"
       class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="info" size="lg" class="align-bottom" />
    </a>
</template>

<script setup lang="ts">
import type { ReplyRecord, SubReplyRecord, ThreadRecord } from '@/api/index.d';
import type { Pid, PostID, Spid, Tid, UnixTimestamp } from '@/shared';
import { tiebaPostLink } from '@/shared';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';

defineProps<{
    post: ReplyRecord | SubReplyRecord | ThreadRecord,
    postTypeID: PostID,
    postIDSelector: () => Pid | Spid | Tid
}>();

const formatTime = (time: UnixTimestamp) => {
    const dateTime = DateTime.fromSeconds(time);
    return `${dateTime.toRelative({ round: false })}（${dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}）`;
};
</script>
