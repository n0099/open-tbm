<template>
    <select @input="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)" :value="modelValue"
            class="form-select form-control flex-grow-0">
        <option>&lt;</option>
        <option>=</option>
        <option>&gt;</option>
        <option>IN</option>
        <option>BETWEEN</option>
    </select>
</template>

<script setup lang="ts">
import { emitEventStrValidator } from '@/shared';

defineProps<{ modelValue: string }>();
defineEmits({ 'update:modelValue': emitEventStrValidator });
</script>

<style scoped>
select {
    width: 7% !important;
}
</style>
