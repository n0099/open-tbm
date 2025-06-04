<template>
<select v-model="modelValue" class="form-select form-control flex-grow-0">
    <option
        v-for="value in numericParamSubParamRangeValues"
        :key="value" :value="value">
        {{ value }}
    </option>
</select>
</template>

<script setup lang="ts">
const modelValue = defineModel<ArrayElement<typeof numericParamSubParamRangeValues>>({ default: '=' });
</script>

<style scoped>
select {
    inline-size: 7% !important;
}
</style>
