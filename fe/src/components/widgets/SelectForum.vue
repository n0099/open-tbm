<template>
    <slot v-if="isFetching || isError" :renderer="indicatorsRenderer" name="indicators">
        <RenderFunction :renderer="indicatorsRenderer" />
    </slot>
    <select v-if="!isFetching && isSuccess && data !== undefined"
            class="form-select form-control" v-bind="$attrs">
        <option value="0">未指定</option>
        <option v-for="forum in data"
                :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
    </select>
</template>

<script setup lang="tsx">
import { useApiForums } from '@/api/index';
import RenderFunction from '@/components/RenderFunction';
import type { VNode } from 'vue';
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });
defineSlots<{ indicators: (props: { renderer: VNode }) => unknown }>();

const { data, error, isFetching, isError, isSuccess } = useApiForums();
const indicatorsRenderer = computed(() => (<>
    {isFetching.value && <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>}
    {isError.value && <span title={JSON.stringify(error)} class="text-danger fw-bold">Error</span>}
</>));
</script>

<style scoped>
.spinner-border {
    height: 1.5rem;
    width: 1.5rem;
}
</style>
