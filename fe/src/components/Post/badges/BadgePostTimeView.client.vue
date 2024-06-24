<template>
<span
    :ref="el => initialTippy([el as Element])" :data-tippy-content="`
            本${postType}${timestampType}：<br>
            ${current.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
            ${relativeTo === undefined || relativeToText === undefined
                ? ''
                : `${relativeToText}：<br>
                    ${relativeTo.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
                    相差 ${current.diff(relativeTo).rescale().toHuman()}`}
          `"
    class="ms-1 fw-normal badge rounded-pill">
    <component :is="$slots.default" />
    {{ current.toRelative({ base: relativeTo, round: false }) }}
</span>
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TPostTimeKey extends keyof TPost
        & ('postedAt' | (TPost extends Thread ? 'latestReplyPostedAt' : never))">
import { DateTime } from 'luxon';

defineProps<{
    current: DateTime<true>,
    relativeTo?: DateTime<true>,
    relativeToText?: string,
    postType: PostTypeTextOf<TPost>,
    timestampType: 'latestReplyPostedAt' extends TPostTimeKey ? '最后回复时间'
        : 'postedAt' extends TPostTimeKey ? '发帖时间' : never
}>();
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
