<template>
    <span :data-tippy-content="tippyPrefix + dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)"
          :class="`ms-1 fw-normal badge rounded-pill bg-${badgeColor}`">
        {{ dateTime.toRelative({ round: false }) }}
    </span>
</template>

<script setup lang="ts">
import type { BootstrapColors, UnixTimestamp } from '@/shared';
import { DateTime } from 'luxon';

const props = withDefaults(defineProps<{
    time: UnixTimestamp,
    tippyPrefix?: string,
    badgeColor: BootstrapColors
}>(), { tippyPrefix: '' });

const dateTime = DateTime.fromSeconds(props.time);
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
