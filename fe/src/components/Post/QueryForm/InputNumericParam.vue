<template>
    <input v-if="modelValue.subParam.range === 'IN'"
           @input="emitModelChange" :value="modelValue.value"
           :placeholder="placeholders.IN" :aria-label="modelValue.name"
           type="text" class="form-control" required pattern="\d+(,\d+)+" />
    <input v-else-if="modelValue.subParam.range === 'BETWEEN'"
           @input="emitModelChange" :value="modelValue.value"
           :placeholder="placeholders.BETWEEN" :aria-label="modelValue.name"
           type="text" class="col-3 form-control flex-grow-0" required pattern="\d+,\d+" />
    <input v-else @input="emitModelChange" :value="modelValue.value"
           :placeholder="placeholders.number" :aria-label="modelValue.name"
           type="number" class="col-2 form-control flex-grow-0" required />
</template>

<script setup lang="ts">
import type { KnownNumericParams } from './queryParams';
import { paramTypeNumericSubParamRangeValues } from './queryParams';
import _ from 'lodash';

const props = defineProps<{
    modelValue: KnownNumericParams,
    placeholders: { [P in 'BETWEEN' | 'IN' | 'number']: string }
}>();
const emit = defineEmits({
    'update:modelValue': (p: KnownNumericParams) =>
        _.isString(p.name) && _.isString(p.value)
        && paramTypeNumericSubParamRangeValues.includes(p.subParam.range)
});

const emitModelChange = (e: Event) => {
    emit('update:modelValue', { ...props.modelValue, value: (e.target as HTMLInputElement).value });
};
</script>

<style scoped>
.col-2 {
    width: 16% !important;
}
.col-3 {
    width: 25% !important;
}
</style>
