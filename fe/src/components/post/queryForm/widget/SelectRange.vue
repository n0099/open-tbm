<template>
<select v-model="modelValue" class="form-select form-control flex-grow-0">
    <option>&lt;</option>
    <option>=</option>
    <option>&gt;</option>
    <option>IN</option>
    <option>BETWEEN</option>
</select>
</template>

<script setup lang="ts">

const modelValue = defineModel<typeof numericParamSubParamRangeValues[number]>({ default: '=' });
</script>

<style scoped>
select {
    inline-size: 7% !important;
}
</style>
