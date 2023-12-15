<template>
    <span :data-tippy-content="tippyPrefix + time"
          :class="`ms-1 fw-normal badge rounded-pill bg-${badgeColor}`">
        {{ DateTime.fromSeconds(time).toRelative({ round: false }) }}
    </span>
</template>

<script setup lang="ts">
import type { BootstrapColors, UnixTimestamp } from '@/shared';
import { DateTime } from 'luxon';

withDefaults(defineProps<{
    time: UnixTimestamp,
    tippyPrefix?: string,
    badgeColor: BootstrapColors
}>(), { tippyPrefix: '' });
</script>

<style scoped>
span {
    padding-left: .75rem;
    padding-right: .75rem;
}
</style>
