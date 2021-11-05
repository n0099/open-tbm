<template>
    <input v-if="modelValue.subParam.range === 'IN'"
           @input="emitModelChange" :value="modelValue.value"
           :class="classes" :placeholder="placeholders.IN" :aria-label="modelValue.name"
           type="text" class="col form-control" required pattern="\d+(,\d+)+" />
    <input v-else-if="modelValue.subParam.range === 'BETWEEN'"
           @input="emitModelChange" :value="modelValue.value"
           :class="classes" :placeholder="placeholders.BETWEEN" :aria-label="modelValue.name"
           type="text" class="col-3 form-control" required pattern="\d+,\d+" />
    <input v-else @input="emitModelChange" :value="modelValue.value"
           :class="classes" :placeholder="placeholders.number" :aria-label="modelValue.name"
           type="number" class="col-2 form-control" required />
</template>

<script lang="ts">
import { defineComponent } from 'vue';

export default defineComponent({
    props: {
        modelValue: { type: Object, required: true },
        classes: { type: Object, required: true },
        placeholders: { type: Object, required: true }
    },
    setup(props, { emit }) {
        const emitModelChange = e => { emit('update:modelValue', { ...props.modelValue, value: e.target.value }) };
        return { emitModelChange };
    }
});
</script>
