<template>
<div class="input-group-text">
    <div class="form-check form-check-inline">
        <input
            @input="emitModelChange('matchBy', ($event.target as HTMLInputElement).value as 'regex')"
            v-bind="inputAttrs('regex')" />
        <label :for="inputID('regex')" class="form-check-label">正则</label>
    </div>
    <div class="form-check form-check-inline">
        <input
            @input="emitModelChange('matchBy', ($event.target as HTMLInputElement).value as 'implicit')"
            v-bind="inputAttrs('implicit')" />
        <label :for="inputID('implicit')" class="form-check-label">模糊</label>
    </div>
    <div class="form-check form-check-inline">
        <input
            @input="emitModelChange('matchBy', ($event.target as HTMLInputElement).value as 'explicit')"
            v-bind="inputAttrs('explicit')" />
        <label :for="inputID('explicit')" class="form-check-label">精确</label>
    </div>
    <div class="form-check form-check-inline">
        <input
            @input="emitModelChange('spaceSplit', ($event.target as HTMLInputElement).checked)"
            v-bind="inputAttrs('spaceSplit')" />
        <label :for="inputID('spaceSplit')" class="form-check-label">空格分隔</label>
    </div>
</div>
</template>

<script lang="ts">
const matchByDescription = {
    implicit: '模糊',
    explicit: '精确',
    regex: '正则'
};
export const inputTextParamPlaceholder = (p: KnownTextParams) =>
    `${matchByDescription[p.subParam.matchBy]}匹配 空格${p.subParam.spaceSplit ? '不能' : ''}分割关键词`;
</script>

<script setup lang="ts">
import _ from 'lodash';

const { paramIndex } = defineProps<{ paramIndex: number }>();
const modelValue = defineModel<KnownTextParams>({
    required: true,
    validator: (p: KnownTextParams) =>
        _.isString(p.name)
        && paramNamesKeyByType.text.includes(p.name)
        && _.isString(p.value)
        && textParamSubParamMatchByValues.includes(p.subParam.matchBy)
        && _.isBoolean(p.subParam.spaceSplit)
});
const emitModelChange = (
    name: keyof NamelessParamText['subParam'],
    value: ObjValues<NamelessParamText['subParam']>
) => {
    modelValue.value = {
        ...modelValue.value,
        subParam: { ...modelValue.value.subParam, [name]: value }
    } as KnownTextParams;
};

type InputType = KnownTextParams['subParam']['matchBy'] | 'spaceSplit';
const inputID = (type: InputType) =>
    `param${_.upperFirst(modelValue.value.name)}${_.upperFirst(type)}-${paramIndex}`;
const inputAttrs = (type: InputType) => ({
    id: inputID(type),
    class: 'form-check-input',
    ...type === 'spaceSplit'
        ? {
            type: 'checkbox',
            checked: modelValue.value.subParam.spaceSplit,
            disabled: modelValue.value.subParam.matchBy === 'regex'
        }
        : {
            type: 'radio',
            checked: modelValue.value.subParam.matchBy === type,
            name: `param${_.upperFirst(modelValue.value.name)}-${paramIndex}`,
            value: type
        }
});
</script>

<style scoped>
.form-check {
    margin: 0;
}
.form-check-inline:not(:last-child) {
    margin-inline-end: .5rem;
}
</style>
