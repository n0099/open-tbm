<template>
    <select @input="$emit('update:modelValue', $event.target.value)" :value="modelValue"
            class="form-select form-control flex-grow-0">
        <option>&lt;</option>
        <option>=</option>
        <option>&gt;</option>
        <option>IN</option>
        <option>BETWEEN</option>
    </select>
</template>

<script lang="ts">
import { emitEventStrValidator } from '@/shared';
import { defineComponent } from 'vue';

export default defineComponent({
    props: {
        modelValue: { type: String, required: true }
    },
    emits: {
        'update:modelValue': emitEventStrValidator
    }
});
</script>

<style scoped>
select {
    width: 7% !important;
}
</style>
