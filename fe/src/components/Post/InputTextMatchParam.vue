<template>
    <div :class="classes" class="input-group-text">
        <div class="custom-radio custom-control custom-control-inline">
            <input @input="emitModelChange('matchBy', $event.target.value)"
                   :checked="modelValue.subParam.matchBy === 'implicit'"
                   :id="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`"
                   :name="`param${_.upperFirst(param.name)}-${paramIndex}`"
                   value="implicit" type="radio" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`" class="custom-control-label">模糊</label>
        </div>
        <div class="custom-radio custom-control custom-control-inline">
            <input @input="emitModelChange('matchBy', $event.target.value)"
                   :checked="modelValue.subParam.matchBy === 'explicit'"
                   :id="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`"
                   :name="`param${_.upperFirst(param.name)}-${paramIndex}`"
                   value="explicit" type="radio" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`" class="custom-control-label">精确</label>
        </div>
        <div class="custom-checkbox custom-control custom-control-inline">
            <input @input="emitModelChange('spaceSplit', $event.target.checked)"
                   :checked="modelValue.subParam.spaceSplit"
                   :id="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`"
                   :disabled="modelValue.subParam.matchBy === 'regex'"
                   type="checkbox" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`" class="custom-control-label">空格分隔</label>
        </div>
        <div class="custom-radio custom-control">
            <input @input="emitModelChange('matchBy', $event.target.value)"
                   :checked="modelValue.subParam.matchBy === 'regex'"
                   :id="`param${_.upperFirst(param.name)}Regex-${paramIndex}`"
                   :name="`param${_.upperFirst(param.name)}-${paramIndex}`"
                   value="regex" type="radio" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}Regex-${paramIndex}`" class="custom-control-label">正则</label>
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import _ from 'lodash';

export default defineComponent({
    props: {
        modelValue: { type: Object, required: true },
        paramIndex: { type: Number, required: true },
        classes: { type: Object, required: true }
    },
    setup(props, { emit }) {
        const emitModelChange = (name, value) => {
            emit('update:modelValue', { ...props.modelValue, ...{ subParam: { [name]: value } } });
        };
        return { _, emitModelChange };
    }
});
</script>
