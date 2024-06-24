<template>
<span
    :title="`本${postType}${timestampType}：\n${
        current.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}`"
    class="ms-1 fw-normal badge rounded-pill">
    <component :is="$slots.default" />
    {{ current.toLocaleString({
        year: 'numeric',
        ...objectWithSameValues(['month', 'day', 'hour', 'minute', 'second'], '2-digit')
    }) }}
</span>
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TPostTimeKey extends keyof TPost
        & ('postedAt' | (TPost extends Thread ? 'latestReplyPostedAt' : never))">
import { DateTime } from 'luxon';

const props = defineProps<{
    current: DateTime<true>,
    postType: PostTypeTextOf<TPost>,
    timestampType: 'latestReplyPostedAt' extends TPostTimeKey ? '最后回复时间'
        : 'postedAt' extends TPostTimeKey ? '发帖时间' : never
}>();
const current = computed(() =>
    props.current.setZone('Asia/Shanghai').setLocale('zh-cn'));
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
