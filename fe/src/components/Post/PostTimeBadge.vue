<template>
    <span :data-tippy-content="tippyPrefix + time"
          :class="`ms-1 fw-normal badge rounded-pill bg-${badgeColor}`">
        {{ dateTimeFromUTC8(time).toRelative({ round: false }) }}
    </span>
</template>

<script setup lang="ts">
import type { BootstrapColors } from '@/shared';
import { dateTimeFromUTC8 } from '@/shared/echarts';

withDefaults(defineProps<{
    time: string,
    tippyPrefix: string,
    badgeColor: BootstrapColors
}>(), { tippyPrefix: '' });
</script>

<style scoped>
span {
    padding-left: .75rem;
    padding-right: .75rem;
}
</style>
