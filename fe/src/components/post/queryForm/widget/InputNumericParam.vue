<template>
<input
    v-if="modelValue.subParam.range === 'IN'"
    @input="emitModelChange($event)" v-bind="inputAttrs('IN')" />
<input
    v-else-if="modelValue.subParam.range === 'BETWEEN'"
    @input="emitModelChange($event)" v-bind="inputAttrs('BETWEEN')" />
<input v-else @input="emitModelChange($event)" v-bind="inputAttrs('equals')" />
</template>

<script setup lang="ts">
import _ from 'lodash';

const { placeholders } = defineProps<{ placeholders: Record<'BETWEEN' | 'IN' | 'equals', string> }>();
const modelValue = defineModel<KnownNumericParams>({
    required: true,
    validator: (p: KnownNumericParams) =>
        (_.isString(p.name) && paramNamesKeyByType.numeric.includes(p.name))
        && ((_.isNumber(p.value)
            && numericParamSubParamRangeSingleValues.includes(p.subParam.range as ArrayElement<typeof numericParamSubParamRangeSingleValues>))
        || (_.isString(p.value)
            && numericParamSubParamRangeMultiValues.includes(p.subParam.range as ArrayElement<typeof numericParamSubParamRangeMultiValues>)))
});
const emitModelChange = (e: Event) => {
    modelValue.value = { ...modelValue.value, value: (e.target as HTMLInputElement).value };
};

const inputAttrs = (type: keyof typeof placeholders) => ({
    // eslint-disable-next-line @typescript-eslint/naming-convention
    'aria-label': modelValue.value.name,
    value: modelValue.value.value,
    placeholder: placeholders[type],
    required: true,
    class: type === 'IN' ? 'form-control' : 'col-3 form-control flex-grow-0',
    // eslint-disable-next-line no-nested-ternary, unicorn/no-nested-ternary
    pattern: type === 'IN' ? '\\d+(,\\d+)+' : type === 'BETWEEN' ? '\\d+,\\d+' : undefined,
    type: type === 'equals' ? 'number' : 'text'
});
</script>

<style scoped>
.col-2 {
    inline-size: 16% !important;
}
.col-3 {
    inline-size: 25% !important;
}
</style>
