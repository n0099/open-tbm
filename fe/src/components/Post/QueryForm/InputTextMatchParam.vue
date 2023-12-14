<template>
    <div class="input-group-text">
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('matchBy', ($event.target as HTMLInputElement).value as 'regex')"
                   :id="inputID('Regex')"
                   :checked="modelValue.subParam.matchBy === 'regex'" :name="inputName"
                   value="regex" type="radio" class="form-check-input" />
            <label :for="inputID('Regex')" class="form-check-label">正则</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('matchBy', ($event.target as HTMLInputElement).value as 'implicit')"
                   :id="inputID('Implicit')"
                   :checked="modelValue.subParam.matchBy === 'implicit'" :name="inputName"
                   value="implicit" type="radio" class="form-check-input" />
            <label :for="inputID('Implicit')" class="form-check-label">模糊</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('matchBy', ($event.target as HTMLInputElement).value as 'explicit')"
                   :id="inputID('Explicit')"
                   :checked="modelValue.subParam.matchBy === 'explicit'" :name="inputName"
                   value="explicit" type="radio" class="form-check-input" />
            <label :for="inputID('Explicit')" class="form-check-label">精确</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('spaceSplit', ($event.target as HTMLInputElement).checked)"
                   :id="inputID('SpaceSplit')"
                   :checked="modelValue.subParam.spaceSplit"
                   :disabled="modelValue.subParam.matchBy === 'regex'"
                   type="checkbox" class="form-check-input" />
            <label :for="inputID('SpaceSplit')" class="form-check-label">空格分隔</label>
        </div>
    </div>
</template>

<script lang="ts">
const matchByDesc = {
    implicit: '模糊',
    explicit: '精确',
    regex: '正则'
};
export const inputTextMatchParamPlaceholder = (p: KnownTextParams) =>
    `${matchByDesc[p.subParam.matchBy]}匹配 空格${p.subParam.spaceSplit ? '不能' : ''}分割关键词`;
</script>

<script setup lang="ts">
import { textParamSubParamMatchByValues } from './queryParams';
import type { KnownTextParams, NamelessParamText } from './queryParams';
import type { ObjValues } from '@/shared';
import _ from 'lodash';

const props = defineProps<{
    modelValue: KnownTextParams,
    paramIndex: number
}>();
const emit = defineEmits({
    'update:modelValue': (p: KnownTextParams) =>
        _.isString(p.name) && _.isString(p.value)
        && textParamSubParamMatchByValues.includes(p.subParam.matchBy)
        && _.isBoolean(p.subParam.spaceSplit)
});

const emitModelChange = (
    name: keyof NamelessParamText['subParam'],
    value: ObjValues<NamelessParamText['subParam']>
) => {
    emit('update:modelValue', {
        ...props.modelValue,
        subParam: { ...props.modelValue.subParam, [name]: value }
    } as KnownTextParams);
};
const inputID = (type: 'Explicit' | 'Implicit' | 'Regex' | 'SpaceSplit') =>
    `param${_.upperFirst(props.modelValue.name)}${type}-${props.paramIndex}`;
const inputName = `param${_.upperFirst(props.modelValue.name)}-${props.paramIndex}`;
</script>

<style scoped>
.form-check {
    margin: 0;
}
.form-check-inline:not(:last-child) {
    margin-right: .5rem;
}
</style>
