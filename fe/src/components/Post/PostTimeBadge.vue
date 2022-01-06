<template>
    <span :data-tippy-content="tippyPrefix + time"
          :class="`ms-1 fw-normal badge rounded-pill bg-${badgeColor}`">
        {{ dateTimeFromUTC8(time).toRelative({ round: false }) }}
    </span>
</template>

<script lang="ts">
import type { BootstrapColors } from '@/shared';
import { dateTimeFromUTC8 } from '@/shared/echarts';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

export default defineComponent({
    props: {
        time: { type: String, required: true },
        tippyPrefix: { type: String, default: '' },
        badgeColor: { type: String as PropType<BootstrapColors>, required: true }
    },
    setup() {
        return { dateTimeFromUTC8 };
    }
});
</script>

<style scoped>
span {
    padding-left: .75rem;
    padding-right: .75rem;
}
</style>
