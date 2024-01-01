<template>
    <div class="input-group-text">
        <div class="form-check form-check-inline">
            <input @input="e => emitModelChange('matchBy', (e.target as HTMLInputElement).value as 'regex')"
                   :checked="modelValue.subParam.matchBy === 'regex'"
                   :name="inputName" value="regex"
                   type="radio" class="form-check-input" :id="inputID('Regex')" />
            <label :for="inputID('Regex')" class="form-check-label">正则</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="e => emitModelChange('matchBy', (e.target as HTMLInputElement).value as 'implicit')"
                   :checked="modelValue.subParam.matchBy === 'implicit'"
                   :name="inputName" value="implicit"
                   type="radio" class="form-check-input" :id="inputID('Implicit')" />
            <label :for="inputID('Implicit')" class="form-check-label">模糊</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="e => emitModelChange('matchBy', (e.target as HTMLInputElement).value as 'explicit')"
                   :checked="modelValue.subParam.matchBy === 'explicit'"
                   :name="inputName" value="explicit"
                   type="radio" class="form-check-input" :id="inputID('Explicit')" />
            <label :for="inputID('Explicit')" class="form-check-label">精确</label>
        </div>
        <div class="form-check form-check-inline">
            <input @input="e => emitModelChange('spaceSplit', (e.target as HTMLInputElement).checked)"
                   :checked="modelValue.subParam.spaceSplit"
                   :disabled="modelValue.subParam.matchBy === 'regex'"
                   type="checkbox" class="form-check-input" :id="inputID('SpaceSplit')" />
            <label :for="inputID('SpaceSplit')" class="form-check-label">空格分隔</label>
        </div>
    </div>
</template>

<script lang="ts">
const matchByDescription = {
    implicit: '模糊',
    explicit: '精确',
    regex: '正则'
};
export const inputTextMatchParamPlaceholder = (p: KnownTextParams) =>
    `${matchByDescription[p.subParam.matchBy]}匹配 空格${p.subParam.spaceSplit ? '不能' : ''}分割关键词`;
</script>

<script setup lang="ts">
import type { KnownTextParams, NamelessParamText } from '../queryParams';
import { textParamSubParamMatchByValues } from '../queryParams';
import type { ObjValues } from '@/shared';
import { computed } from 'vue';
import * as _ from 'lodash';

const props = defineProps<{ paramIndex: number }>();
// eslint-disable-next-line vue/define-emits-declaration
defineEmits({
    // eslint-disable-next-line vue/no-unused-emit-declarations
    'update:modelValue': (p: KnownTextParams) =>
        _.isString(p.name) && _.isString(p.value)
        && textParamSubParamMatchByValues.includes(p.subParam.matchBy)
        && _.isBoolean(p.subParam.spaceSplit)
});
const modelValue = defineModel<KnownTextParams>({ required: true });

const emitModelChange = (
    name: keyof NamelessParamText['subParam'],
    value: ObjValues<NamelessParamText['subParam']>
) => {
    modelValue.value = {
        ...modelValue.value,
        subParam: { ...modelValue.value.subParam, [name]: value }
    } as KnownTextParams;
};
const inputID = (type: 'Explicit' | 'Implicit' | 'Regex' | 'SpaceSplit') =>
    `param${_.upperFirst(modelValue.value.name)}${type}-${props.paramIndex}`;
const inputName = computed(() =>
    `param${_.upperFirst(modelValue.value.name)}-${props.paramIndex}`);
</script>

<style scoped>
.form-check {
    margin: 0;
}
.form-check-inline:not(:last-child) {
    margin-inline-end: .5rem;
}
</style>
