<template>
    <code class="text-primary-emphasis">{{ postIDKey }}:{{ props.post[props.postIDKey] }}</code>
    <RouterLink v-if="postIDKey === 'tid' || postIDKey === 'pid'"
                :to="{ hash: `#${postIDKey === 'tid' ? 't' : ''}${props.post[props.postIDKey]}` }"
                :data-tippy-content="`跳至本${postTypeText}链接`"
                class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="hashtag" size="lg" class="align-bottom" />
    </RouterLink>
    <RouterLink :to="{
                    name: `post/${postIDKey}`,
                    params: { [props.postIDKey]: props.post[props.postIDKey] as Tid | Pid | Spid }
                }"
                :data-tippy-content="`固定链接/只看此${postTypeText}`"
                class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="link" size="lg" class="align-bottom" />
    </RouterLink>
    <a :href="tiebaPostLink(props.post.tid,
                            (props.post as Reply | SubReply).pid,
                            (props.post as SubReply).spid)"
       class="badge bg-light rounded-pill link-dark" data-tippy-content="在贴吧中查看" target="_blank">
        <FontAwesomeIcon icon="arrow-up-right-from-square" size="lg" class="align-bottom" />
    </a>
    <span :data-tippy-content="`
            首次收录时间：${formatTime(props.post.createdAt)}<br />
            最后更新时间：${formatTime(props.post.updatedAt ?? props.post.createdAt)}<br />
            最后发现时间：${formatTime(props.post.lastSeenAt ?? props.post.updatedAt ?? props.post.createdAt)}`"
          class="badge bg-light rounded-pill link-dark">
        <FontAwesomeIcon icon="clock" size="lg" class="align-bottom" />
    </span>
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TPostIDKey extends keyof TPost & PostIDOf<TPost>">
import type { Reply, SubReply } from '@/api/post';
import type { Pid, Post, PostIDOf, PostTypeTextOf, Spid, Tid, UnixTimestamp } from '@/shared';
import { tiebaPostLink } from '@/shared';
import { RouterLink } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';

// https://github.com/vuejs/language-tools/issues/3267
const props = defineProps<{
    post: TPost,
    postIDKey: TPostIDKey,
    postTypeText: PostTypeTextOf<TPost>
}>();
const formatTime = (time: UnixTimestamp) => {
    const dateTime = DateTime.fromSeconds(time);
    const relative = dateTime.toRelative({ round: false });
    const fullWithLocale = dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);

    return `${relative} ${fullWithLocale}`;
};
</script>
