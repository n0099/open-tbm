<template>
    <span :data-tippy-content="tippyPrefix + dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)"
          class="ms-1 fw-normal badge rounded-pill">
        {{ dateTime.toRelative({ base, round: false }) }}
    </span>
</template>

<script setup lang="ts">
import type { UnixTimestamp } from '@/shared';
import { computed } from 'vue';
import { DateTime } from 'luxon';

const props = withDefaults(defineProps<{
    time: UnixTimestamp,
    base?: UnixTimestamp,
    tippyPrefix?: string
}>(), { tippyPrefix: '' });

const dateTime = computed(() => DateTime.fromSeconds(props.time));
const base = computed(() =>
    (props.base === undefined ? undefined : DateTime.fromSeconds(props.base)));
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
