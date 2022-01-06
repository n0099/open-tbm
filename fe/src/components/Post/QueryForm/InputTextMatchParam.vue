<template>
    <div class="input-group-text">
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
        <div class="form-check form-check-inline">
            <input @input="emitModelChange('matchBy', $event.target.value)"
                   :id="inputID('Regex')"
                   :checked="modelValue.subParam.matchBy === 'regex'" :name="inputName"
                   value="regex" type="radio" class="form-check-input" />
            <label :for="inputID('Regex')" class="form-check-label">正则</label>
        </div>
    </div>
</template>

<script lang="ts">
import type { ParamTypeText, ParamTypeWithCommon } from './queryParams';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import _ from 'lodash';

export default defineComponent({
    props: {
        modelValue: Object as PropType<ParamTypeWithCommon<string, ParamTypeText>>,
        paramIndex: { type: Number, required: true }
    },
    setup(props, { emit }) {
        const emitModelChange = (name: string, value: unknown) => {
            emit('update:modelValue', { ...props.modelValue, ...{ subParam: { [name]: value } } });
        };
        const inputID = (type: 'Explicit' | 'Implicit' | 'Regex' | 'SpaceSplit') =>
            `param${_.upperFirst(props.modelValue?.name)}${type}-${props.paramIndex}`;
        const inputName = `param${_.upperFirst(props.modelValue?.name)}-${props.paramIndex}`;
        return { emitModelChange, inputID, inputName };
    }
});
</script>

<style scoped>
.form-check {
    margin: 0;
}
.form-check-inline:not(:last-child) {
    margin-right: 0.5rem;
}
</style>
