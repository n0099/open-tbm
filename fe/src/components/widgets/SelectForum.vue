<template>
    <slot v-if="isFetching || isError" :renderer="indicatorsRenderer" name="indicators">
        <RenderFunction :renderer="indicatorsRenderer" />
    </slot>
    <select v-if="!isFetching && isSuccess && data !== undefined" v-model="modelValue"
            class="form-select form-control" v-bind="$attrs">
        <option value="0">未指定</option>
        <option v-for="forum in data"
                :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
    </select>
</template>

<script setup lang="tsx">
import { useApiForums } from '@/api/index';
import type { Fid } from '@/utils';
import type { VNode } from 'vue';

defineOptions({ inheritAttrs: false });
defineSlots<{ indicators: (props: { renderer: VNode }) => unknown }>();
const modelValue = defineModel<Fid>();

const { data, error, isFetching, isError, isSuccess } = useApiForums();
const indicatorsRenderer = computed(() => (<>
    {isFetching.value && <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>}
    {isError.value && <span title={JSON.stringify(error.value?.message)} class="text-danger fw-bold">Error</span>}
</>));
</script>

<style scoped>
.spinner-border {
    height: 1.5rem;
    width: 1.5rem;
}
</style>
