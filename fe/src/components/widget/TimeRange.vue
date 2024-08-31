<template>
<ARangePicker
    v-model="timeRange" :ranges="{
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
import type { Dayjs } from 'dayjs';
import dayjs, { unix } from 'dayjs';
import type { DurationLike } from 'luxon';
import { DateTime } from 'luxon';
import _ from 'lodash';

defineOptions({ inheritAttrs: true });
const props = withDefaults(defineProps<{
    startTime?: number,
    endTime?: number,
    startBefore: DurationLike
}>(), {
    startTime: 0,
    endTime: 0
});
// eslint-disable-next-line vue/define-emits-declaration
const emit = defineEmits({
    'update:startTime': i => _.isNumber(i),
    'update:endTime': i => _.isNumber(i)
});

const timeRange = ref<[Dayjs, Dayjs]>((now => [
    dayjs(now.minus(props.startBefore).startOf('minute').toISO()),
    dayjs(now.startOf('minute').toISO())
])(DateTime.now()));

watchEffect(() => {
    timeRange.value = [unix(props.startTime), unix(props.endTime)];
});
watchEffect(() => {
    emit('update:startTime', timeRange.value[0].unix());
    emit('update:endTime', timeRange.value[1].unix());
});
</script>
