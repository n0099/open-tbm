<template>
    <select @input="$emit('input', $event.target.value)" :value="value" class="col-1 custom-select form-control">
        <option>&lt;</option>
        <option>=</option>
        <option>&gt;</option>
        <option>IN</option>
        <option>BETWEEN</option>
    </select>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

    }
});

const selectRangeComponent = Vue.component('select-range', {
    template: '#select-range-template',
    props: {
        value: { type: String, required: true }
    }
});
</script>

<style scoped>

</style>
