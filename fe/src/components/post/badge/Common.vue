<template>
<code class="text-primary-emphasis">{{ postIDKey }}:{{ props.post[props.postIDKey] }}</code>
<NuxtLink
    v-if="postIDKey === 'tid' || postIDKey === 'pid'"
    v-tippy="`跳至本${postTypeText}链接`"
    :to="{ hash: `#${postIDKey === 'tid' ? 't' : ''}${props.post[props.postIDKey]}` }"
    class="badge bg-light rounded-pill link-dark">
    <FontAwesome :icon="faHashtag" size="lg" class="align-bottom" />
</NuxtLink>
<NuxtLink
    v-tippy="`固定链接/只看此${postTypeText}`"
    :to="{
        name: `posts/${postIDKey}`,
        params: { [props.postIDKey]: props.post[props.postIDKey] as Tid | Pid | Spid }
    }"
    class="badge bg-light rounded-pill link-dark">
    <FontAwesome :icon="faLink" size="lg" class="align-bottom" />
</NuxtLink>
<NuxtLink
    v-tippy="'在贴吧中查看'"
    :to="tiebaPostLink(props.post.tid,
                       (props.post as Reply | SubReply).pid,
                       (props.post as SubReply).spid)"
    class="badge bg-light rounded-pill link-dark" target="_blank">
    <FontAwesome :icon="faArrowUpRightFromSquare" size="lg" class="align-bottom" />
</NuxtLink>
<span
    v-tippy="`
        首次收录时间：${formatTime(props.post.createdAt)}<br>
        最后更新时间：${formatTime(props.post.updatedAt ?? props.post.createdAt)}<br>
        最后发现时间：${formatTime(props.post.lastSeenAt ?? props.post.updatedAt ?? props.post.createdAt)}`"
    class="badge bg-light rounded-pill link-dark">
    <FontAwesome :icon="faClock" size="lg" class="align-bottom" />
</span>
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TPostIDKey extends keyof TPost & PostIDOf<TPost>">
import { faClock } from '@fortawesome/free-regular-svg-icons';
import { faArrowUpRightFromSquare, faHashtag, faLink } from '@fortawesome/free-solid-svg-icons';
import { DateTime } from 'luxon';

// https://github.com/vuejs/language-tools/issues/3267
const props = defineProps<{
    post: TPost,
    postIDKey: TPostIDKey,
    postTypeText: PostTypeTextOf<TPost>
}>();
const formatTime = (time: UnixTimestamp) => {
    const dateTime = DateTime.fromSeconds(time);
    const relative = import.meta.client ? dateTime.toRelative({ round: false }) : '';
    const full = import.meta.server
        ? setDateTimeZoneAndLocale()(dateTime)
            .toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)
        : dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);

    return `${relative} ${full}`;
};
</script>
