<template>
    <span :data-tippy-content="tippyPrefix + time"
          :class="'badge rounded-pill bg-' + badgeColor">
        {{ dateTimeFromUTC8(time).toRelative({ round: false }) }}
    </span>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { BootstrapColors } from '@/shared';
import { dateTimeFromUTC8 } from '@/shared/echarts';

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
    padding-left: 1rem;
    padding-right: 1rem;
}
</style>
