<template>
    <div class="input-group-text">
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('matchBy', $event.target.value)"
                   :id="inputID('Regex')"
                   :checked="modelValue.subParam.matchBy === 'regex'" :name="inputName"
                   value="regex" type="radio" class="form-check-input" />
            <label :for="inputID('Regex')" class="form-check-label">正则</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('matchBy', $event.target.value)"
                   :id="inputID('Implicit')"
                   :checked="modelValue.subParam.matchBy === 'implicit'" :name="inputName"
                   value="implicit" type="radio" class="form-check-input" />
            <label :for="inputID('Implicit')" class="form-check-label">模糊</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('matchBy', $event.target.value)"
                   :id="inputID('Explicit')"
                   :checked="modelValue.subParam.matchBy === 'explicit'" :name="inputName"
                   value="explicit" type="radio" class="form-check-input" />
            <label :for="inputID('Explicit')" class="form-check-label">精确</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('spaceSplit', $event.target.checked)"
                   :id="inputID('SpaceSplit')"
                   :checked="modelValue.subParam.spaceSplit"
                   :disabled="modelValue.subParam.matchBy === 'regex'"
                   type="checkbox" class="form-check-input" />
            <label :for="inputID('SpaceSplit')" class="form-check-label">空格分隔</label>
        </div>
    </div>
</template>

<script lang="ts">
import type { Params, paramsNameByType } from '@/components/Post/QueryForm/queryParams.ts';

const matchByDesc = {
    implicit: '模糊',
    explicit: '精确',
    regex: '正则'
};
export const inputTextMatchParamPlaceholder = (p: Params[typeof paramsNameByType.text[number]]) =>
    `${matchByDesc[p.subParam.matchBy]}匹配 空格${p.subParam.spaceSplit ? '不能' : ''}分割关键词`;
</script>

<script setup lang="ts">
import { paramTypeTextSubParamMatchByValues } from './queryParams';
import type { ParamTypeText, ParamTypeWithCommon } from './queryParams';
import type { ObjValues } from '@/shared';
import _ from 'lodash';

type ParamType = ParamTypeWithCommon<string, ParamTypeText>;

const props = defineProps<{
    modelValue: ParamType,
    paramIndex: number
}>();
const emit = defineEmits({
    'update:modelValue': (p: ParamType) =>
        _.isString(p.name) && _.isString(p.value)
        && paramTypeTextSubParamMatchByValues.includes(p.subParam.matchBy)
        && _.isBoolean(p.subParam.spaceSplit)
});

const emitModelChange = (name: keyof ParamTypeText['subParam'], value: ObjValues<ParamTypeText['subParam']>) => {
    emit('update:modelValue', {
        ...props.modelValue,
        subParam: { ...props.modelValue.subParam, [name]: value }
    } as ParamType);
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
