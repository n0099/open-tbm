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

<script lang="ts">
import type { ParamTypeNumeric, ParamTypeWithCommon } from './queryParams';
import { paramTypeNumericSubParamRangeValues } from './queryParams';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import _ from 'lodash';

export default defineComponent({
    props: {
        modelValue: { type: Object as PropType<ParamTypeWithCommon<string, ParamTypeNumeric>>, required: true },
        placeholders: { type: Object as PropType<{ [P in 'BETWEEN' | 'IN' | 'number']: string }>, required: true }
    },
    emits: {
        'update:modelValue': (p: ParamTypeWithCommon<string, ParamTypeNumeric>) =>
            _.isString(p.name) && _.isString(p.value)
                && paramTypeNumericSubParamRangeValues.includes(p.subParam.range)
    },
    setup(props, { emit }) {
        const emitModelChange = (e: InputEvent & { target: HTMLInputElement }) => {
            emit('update:modelValue', { ...props.modelValue, value: e.target.value });
        };
        return { emitModelChange };
    }
});
</script>

<style scoped>
.col-2 {
    width: 16% !important;
}
.col-3 {
    width: 25% !important;
}
</style>
