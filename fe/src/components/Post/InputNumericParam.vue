<template>
    <input v-if="param.subParam.range === 'IN'" @input="modelEvent($event.target.value)" :value="param.value"
           :class="classes" :placeholder="placeholders.IN" :aria-label="param.name"
           type="text" class="col form-control" required pattern="\d+(,\d+)+" />
    <input v-else-if="param.subParam.range === 'BETWEEN'" @input="modelEvent($event.target.value)" :value="param.value"
           :class="classes" :placeholder="placeholders.BETWEEN" :aria-label="param.name"
           type="text" class="col-3 form-control" required pattern="\d+,\d+" />
    <input v-else @input="modelEvent($event.target.value)" :value="param.value"
           :class="classes" :placeholder="placeholders.number" :aria-label="param.name"
           type="number" class="col-2 form-control" required />
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

    }
});

const inputNumericParamComponent = Vue.component('input-numeric-param', {
    template: '#input-numeric-param-template',
    model: {
        prop: 'param'
    },
    props: {
        param: { type: Object, required: true },
        classes: { type: Object, required: true },
        placeholders: { type: Object, required: true }
    },
    methods: {
        modelEvent (value) {
            this.$props.param.value = value;
            this.$emit('input', this.$props.param);
        }
    }
});
</script>

<style scoped>

</style>
