<template>
    <a :href="tiebaPostLink(props.post.tid,
                            (props.post as Reply | SubReply).pid,
                            (props.post as SubReply).spid)"
       target="_blank" class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="link" size="lg" class="align-bottom" />
    </a>
    <a :data-tippy-content="`<h6>${postIDKey}：${props.post[props.postIDKey]}</h6><hr />
        首次收录时间：${formatTime(props.post.createdAt)}<br />
        最后更新时间：${formatTime(props.post.updatedAt ?? props.post.createdAt)}<br />
        最后发现时间：${formatTime(props.post.lastSeenAt ?? props.post.updatedAt ?? props.post.createdAt)}`"
       class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="info" size="lg" class="align-bottom" />
    </a>
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TPostIDKey extends keyof TPost & PostIDOf<TPost>">
import type { Reply, SubReply } from '@/api/post';
import type { Post, PostIDOf, UnixTimestamp } from '@/shared';
import { tiebaPostLink } from '@/shared';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';

// https://github.com/vuejs/language-tools/issues/3267
const props = defineProps<{
    post: TPost,
    postIDKey: TPostIDKey
}>();
const formatTime = (time: UnixTimestamp) => {
    const dateTime = DateTime.fromSeconds(time);
    const relative = dateTime.toRelative({ round: false });
    const fullWithLocale = dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);

    return `${relative} ${fullWithLocale}`;
};
</script>
