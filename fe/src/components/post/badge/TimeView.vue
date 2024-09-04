<template>
<time
    v-tippy="tippyContent" :datetime="currentInChina.toISO() ?? undefined"
    class="ms-1 fw-normal badge rounded-pill user-select-all">
    <component :is="$slots.default" />
    <RelativeTime :dateTime="current" :relativeTo="relativeTo" />
</time>
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

const currentInChina = computed(() => setDateTimeZoneAndLocale()(props.current));
const tippyContentRelativeTo = computed(() => {
    if (props.relativeTo === undefined || props.relativeToText === undefined)
        return '';

    const relativeToLocale = props.relativeTo
        .toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
    const currentDiffRelativeHuman = props.current
        .diff(props.relativeTo, undefined, { conversionAccuracy: 'longterm' })
        .rescale().toHuman();

    return `
        ${props.relativeToText}：<br>
        <span class="user-select-all">${relativeToLocale}</span>
        UNIX:<span class="user-select-all">${props.relativeTo.toUnixInteger()}</span><br>
        相差 <span class="user-select-all">${currentDiffRelativeHuman}</span>`;
});
const tippyContent = () => {
    const currentText = () => {
        if (hydrationStore.isHydratingOrSSR)
            return currentInChina.value.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
        const currentLocale = props.current.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);

        return tippyContentRelativeTo.value === ''
            ? `
                UNIX:<span class="user-select-all">${props.current.toUnixInteger()}</span><br>
                <span class="user-select-all">${currentLocale}</span>`
            : `
                <span class="user-select-all">${currentLocale}</span>
                UNIX:<span class="user-select-all">${props.current.toUnixInteger()}</span>`;
    };

    return `
        本${props.postType}${props.timestampType}：<br>
        ${currentText()}<br>
        ${tippyContentRelativeTo.value}`;
};
</script>

<style scoped>
:deep(.relative-time-placeholder) {
    width: 2.5rem;
}
</style>
