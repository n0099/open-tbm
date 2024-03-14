<template>
    <DefineTemplate v-slot="{ base, time, tippyPrefix }">
        <span :data-tippy-content="`
            ${base === undefined ? '' : `${base.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>`}
            ${tippyPrefix}<br>
            ${time.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}`"
              class="ms-1 fw-normal badge rounded-pill" v-bind="$attrs">
            {{ time.toRelative({
                base,
                round: false
            }) }}
        </span>
    </DefineTemplate>
    <ReuseTemplate v-if="previousPostDateTime !== undefined"
                   :time="currentPostDateTime" :base="previousPostDateTime" :tippyPrefix="`相对于上一帖${timestampType}`" />
    <ReuseTemplate :time="currentPostDateTime" :tippyPrefix="`${timestampType}：`" />
    <ReuseTemplate v-if="nextPostDateTime !== undefined"
                   :time="nextPostDateTime" :base="currentPostDateTime" :tippyPrefix="`相对于下一帖${timestampType}`" />
</template>

<script setup lang="ts">
import type { UnixTimestamp } from '@/shared';
import { computed } from 'vue';
import { createReusableTemplate } from '@vueuse/core';
import { DateTime } from 'luxon';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    timestampType: string,
    previousPostTime?: UnixTimestamp,
    currentPostTime: UnixTimestamp,
    nextPostTime?: UnixTimestamp
}>();

const [DefineTemplate, ReuseTemplate] = createReusableTemplate<{
    base?: DateTime<true>,
    time: DateTime<true>,
    tippyPrefix: string
}>();
const previousPostDateTime = computed(() =>
    (props.previousPostTime === undefined ? undefined : DateTime.fromSeconds(props.previousPostTime)));
const currentPostDateTime = computed(() => DateTime.fromSeconds(props.currentPostTime));
const nextPostDateTime = computed(() =>
    (props.nextPostTime === undefined ? undefined : DateTime.fromSeconds(props.nextPostTime)));
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
