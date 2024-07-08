<template>
<span
    v-if="!hydrationStore.isHydratingOrSSR"
    v-tippy="`
            本${postType}${timestampType}：<br>
            ${current.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
            ${relativeTo === undefined || relativeToText === undefined
                ? ''
                : `${relativeToText}：<br>
                    ${relativeTo.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
                    相差 ${current.diff(relativeTo).rescale().toHuman()}`}
          `"
    class="ms-1 fw-normal badge rounded-pill" v-bind="$attrs">
    <component :is="$slots.default" />
    {{ current.toRelative({ base: relativeTo, round: false }) }}
</span>
<span
    v-if="hydrationStore.isHydratingOrSSR"
    :title="`本${postType}${timestampType}：\n
        ${currentInChina.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}`"
    class="ms-1 fw-normal badge rounded-pill" v-bind="$attrs">
    <component :is="$slots.default" />
    {{ currentInChina.toLocaleString({
        year: 'numeric',
        ...keysWithSameValue(['month', 'day', 'hour', 'minute', 'second'], '2-digit')
    }) }}
</span>
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TPostTimeKey extends keyof TPost
        & ('postedAt' | (TPost extends Thread ? 'latestReplyPostedAt' : never))">
import { DateTime } from 'luxon';

defineOptions({ inheritAttrs: false });
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
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
