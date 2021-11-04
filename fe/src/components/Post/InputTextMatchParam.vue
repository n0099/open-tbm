<template>
    <div :class="classes" class="input-group-text">
        <div class="custom-radio custom-control custom-control-inline">
            <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'implicit'" :id="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`"
                   :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="implicit" type="radio" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`" class="custom-control-label">模糊</label>
        </div>
        <div class="custom-radio custom-control custom-control-inline">
            <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'explicit'" :id="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`"
                   :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="explicit" type="radio" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`" class="custom-control-label">精确</label>
        </div>
        <div class="custom-checkbox custom-control custom-control-inline">
            <input @input="modelEvent('spaceSplit', $event.target.checked)" :checked="param.subParam.spaceSplit" :id="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`"
                   :disabled="param.subParam.matchBy === 'regex'" type="checkbox" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`" class="custom-control-label">空格分隔</label>
        </div>
        <div class="custom-radio custom-control">
            <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'regex'" :id="`param${_.upperFirst(param.name)}Regex-${paramIndex}`"
                   :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="regex" type="radio" class="custom-control-input">
            <label :for="`param${_.upperFirst(param.name)}Regex-${paramIndex}`" class="custom-control-label">正则</label>
        </div>
    </div>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

    }
});

const inputTextMatchParamComponent = Vue.component('input-text-match-param', {
    template: '#input-text-match-param-template',
    model: {
        prop: 'param'
    },
    props: {
        param: { type: Object, required: true },
        paramIndex: { type: Number, required: true },
        classes: { type: Object, required: true }
    },
    methods: {
        modelEvent (name, value) {
            this.$props.param.subParam[name] = value;
            this.$emit('input', this.$props.param);
        }
    }
});
</script>

<style scoped>

</style>
