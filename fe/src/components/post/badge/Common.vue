<template>
<code class="text-primary-emphasis">
    {{ postIDKey }}:<span class="user-select-all">{{ props.post[props.postIDKey] }}</span>
</code>
<NuxtLink
    v-tippy="`跳至本${postTypeText}链接`"
    :to="{
        hash: `#${postIDKey}/${props.post[props.postIDKey]}`,
        name: currentCursor === '' ? routeNameWithoutCursor(route.name) : routeNameWithCursor(route.name),
        params: { cursor: undefinedWhenEmpty(currentCursor) }
    }"
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
    :key="tippyContent" v-tippy="tippyContent"
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
const route = useRoute();
const { currentCursor } = usePostPageProvision().inject();
const relativeTimeStore = useRelativeTimeStore();
const formatTime = (time: UnixTimestamp) => {
    const dateTime = DateTime.fromSeconds(time);
    const relative = import.meta.client ? relativeTimeStore.registerRelative(dateTime).value : '';
    const locale = import.meta.server
        ? setDateTimeZoneAndLocale()(dateTime)
            .toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)
        : dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);

    return `
        <span class="user-select-all">${relative}</span>
        <span class="user-select-all">${locale}</span>
        UNIX:<span class="user-select-all">${time}</span>`;
};

// https://github.com/vuejs/core/issues/8034
// https://stackoverflow.com/questions/77913255/can-we-two-way-bind-in-custom-directives
const tippyContent = computed(() => `
首次收录时间：${formatTime(props.post.createdAt)}<br>
最后更新时间：${formatTime(props.post.updatedAt ?? props.post.createdAt)}<br>
最后发现时间：${formatTime(props.post.lastSeenAt ?? props.post.updatedAt ?? props.post.createdAt)}`);
</script>
