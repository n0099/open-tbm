<template>
<time
    v-tippy="tippyContent" :datetime="currentInShanghai.toISO() ?? undefined"
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

const { current, relativeTo, relativeToText, postType, timestampType } = defineProps<{
    current: DateTime<true>,
    relativeTo?: DateTime<true>,
    relativeToText?: string,
    postType: PostTypeTextOf<TPost>,
    timestampType: 'latestReplyPostedAt' extends TPostTimeKey ? '最后回复时间'
        : 'postedAt' extends TPostTimeKey ? '发帖时间' : never
}>();
const hydrationStore = useHydrationStore();
const currentInShanghai = computed(() => setDateTimeZoneAndLocale()(current));
const placeholderWidth = computed(() => (dateTimeLocale.value.startsWith('zh') ? '' : '2.5rem'));

const tippyContentRelativeTo = computed(() => {
    if (relativeTo === undefined || relativeToText === undefined)
        return '';

    const relativeToLocale = relativeTo
        .toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
    const currentDiffRelativeHuman = current
        .diff(relativeTo, undefined, { conversionAccuracy: 'longterm' })
        .rescale().toHuman();

    return `
        ${relativeToText}：<br>
        <span class="user-select-all">${relativeToLocale}</span>
        UNIX:<span class="user-select-all">${relativeTo.toUnixInteger()}</span><br>
        相差 <span class="user-select-all">${currentDiffRelativeHuman}</span>`;
});
const tippyContent = () => {
    const currentText = () => {
        if (hydrationStore.isHydratingOrSSR)
            return currentInShanghai.value.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
        const currentLocale = current.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);

        return tippyContentRelativeTo.value === ''
            ? `
                UNIX:<span class="user-select-all">${current.toUnixInteger()}</span><br>
                <span class="user-select-all">${currentLocale}</span>`
            : `
                <span class="user-select-all">${currentLocale}</span>
                UNIX:<span class="user-select-all">${current.toUnixInteger()}</span>`;
    };

    return `
        本${postType}${timestampType}：<br>
        ${currentText()}<br>
        ${tippyContentRelativeTo.value}`;
};
</script>

<style scoped>
:deep(.relative-time-placeholder) {
    width: v-bind(placeholderWidth);
}
</style>
