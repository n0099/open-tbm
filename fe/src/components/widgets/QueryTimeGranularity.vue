<template>
    <select @input="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
            :id="id" :value="modelValue" class="form-control">
        <option v-for="(text, granularity) in options" :key="granularity" :value="granularity">{{ text }}</option>
    </select>
</template>

<script setup lang="ts">
import { emitEventStrValidator } from '@/shared';
import type { TimeGranularitiesStringMap } from '@/shared/echarts';
import { ref } from 'vue';
import _ from 'lodash';

const props = withDefaults(defineProps<{
    modelValue: string,
    id?: string,
    granularities: readonly string[]
}>(), { id: 'queryTimeGranularity' });
defineEmits({
    'update:modelValue': emitEventStrValidator
});

const defaultOptions: TimeGranularitiesStringMap = {
    minute: '分钟',
    hour: '小时',
    day: '天',
    week: '周',
    month: '月',
    year: '年'
};
const options = ref<TimeGranularitiesStringMap>({});
options.value = _.pick(defaultOptions, _.intersection(props.granularities, Object.keys(defaultOptions)));
</script>
