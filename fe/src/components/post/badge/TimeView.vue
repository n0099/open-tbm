<template>
<span v-tippy="tippyContent" class="ms-1 fw-normal badge rounded-pill">
    <component :is="$slots.default" />
    <template v-if="hydrationStore.isHydratingOrSSR">
        {{ currentInChina.toLocaleString({
            year: 'numeric',
            ...keysWithSameValue(['month', 'day', 'hour', 'minute', 'second'], '2-digit')
        }) }}
    </template>
    <template v-else>
        {{ relativeTo === undefined
            ? relativeTimeStore.registerRelative(current)
            : current.toRelative({ base: relativeTo, round: false }) }}
    </template>
</span>
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TPostTimeKey extends keyof TPost
        & ('postedAt' | (TPost extends Thread ? 'latestReplyPostedAt' : never))">
import { DateTime } from 'luxon';

const props = defineProps<{
    current: DateTime<true>,
    relativeTo?: DateTime<true>,
    relativeToText?: string,
    postType: PostTypeTextOf<TPost>,
    timestampType: 'latestReplyPostedAt' extends TPostTimeKey ? '最后回复时间'
        : 'postedAt' extends TPostTimeKey ? '发帖时间' : never
}>();
const hydrationStore = useHydrationStore();
const relativeTimeStore = useRelativeTimeStore();

const currentInChina = computed(() => setDateTimeZoneAndLocale()(props.current));
const tippyCotentRelativeTo = computed(() => (props.relativeTo === undefined || props.relativeToText === undefined
    ? ''
    : `${props.relativeToText}：<br>
        ${props.relativeTo.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
        相差 ${props.current.diff(props.relativeTo).rescale().toHuman()}`));
const tippyContent = computed(() => `本${props.postType}${props.timestampType}：<br>
${hydrationStore.isHydratingOrSSR
        ? currentInChina.value.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)
        : `${props.current.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
        ${tippyCotentRelativeTo.value}`
}`);
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
