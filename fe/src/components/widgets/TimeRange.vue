<template>
    <RangePicker @change="(value, _) => timeRangeChanged(value as [Dayjs, Dayjs])"
                 :value="timeRange" :ranges="{
                     昨天: [dayjs().subtract(1, 'day').startOf('day'), dayjs().subtract(1, 'day').endOf('day')],
                     今天: [dayjs().startOf('day'), dayjs().endOf('day')],
                     本周: [dayjs().startOf('week'), dayjs().endOf('week')],
                     最近7天: [dayjs().subtract(7, 'days'), dayjs()],
                     本月: [dayjs().startOf('month'), dayjs().endOf('month')],
                     最近30天: [dayjs().subtract(30, 'days'), dayjs()]
                 }" format="YYYY-MM-DD HH:mm" :showTime="{
                     format: 'HH:mm',
                     minuteStep: 5,
                     secondStep: 10
                 }" :allowClear="false" size="large" class="col" />
</template>

<script setup lang="ts">
import { emitEventWithNumberValidator } from '@/shared';
import { ref, watchEffect } from 'vue';
import { RangePicker } from 'ant-design-vue';
import type { DurationLike } from 'luxon';
import { DateTime } from 'luxon';
import type { Dayjs } from 'dayjs';
import dayjs, { unix } from 'dayjs';

defineOptions({ inheritAttrs: true });
const props = withDefaults(defineProps<{
    startTime?: number,
    endTime?: number,
    timesAgo: DurationLike
}>(), {
    startTime: 0,
    endTime: 0
});
const emit = defineEmits({
    'update:startTime': emitEventWithNumberValidator,
    'update:endTime': emitEventWithNumberValidator
});

const timeRange = ref<[Dayjs, Dayjs]>([dayjs(), dayjs()]);
const timeRangeChanged = ([startTime, endTime]: [Dayjs, Dayjs]) => {
    emit('update:startTime', startTime.unix());
    emit('update:endTime', endTime.unix());
};

watchEffect(() => {
    timeRange.value = [unix(props.startTime), unix(props.endTime)];
});
const initialRangeWithTimesAgo: [Dayjs, Dayjs] = [
    dayjs(DateTime.now().minus(props.timesAgo).startOf('minute').toISO()),
    dayjs(DateTime.now().startOf('minute').toISO())
];

// timesAgo will overwrite first assign to timeRange with initial props value
timeRange.value = initialRangeWithTimesAgo;
timeRangeChanged(initialRangeWithTimesAgo);
</script>
