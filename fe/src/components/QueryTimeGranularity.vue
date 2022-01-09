<template>
    <select @input="$emit('update:modelValue', $event.target.value)"
            :id="id" :value="modelValue" class="form-control">
        <option v-for="(text, granularity) in options" :key="granularity" :value="granularity">{{ text }}</option>
    </select>
</template>

<script lang="ts">
import { emitEventStrValidator } from '@/shared';
import type { TimeGranularitiesStringMap } from '@/shared/echarts';
import type { PropType } from 'vue';
import { defineComponent, ref } from 'vue';
import _ from 'lodash';

export default defineComponent({
    props: {
        modelValue: { type: String, required: true },
        id: { type: String, default: 'queryTimeGranularity' },
        granularities: { type: Array as PropType<string[]>, required: true }
    },
    emits: {
        'update:modelValue': emitEventStrValidator
    },
    setup(props) {
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
        return { options };
    }
});
</script>
