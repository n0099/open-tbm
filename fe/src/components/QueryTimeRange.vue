<template>
    <RangePicker @change="timeRangeChanged" :id="id" :value="timeRange" :ranges="{
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

<script lang="ts">
import { emitEventNumValidator } from '@/shared';
import type { PropType } from 'vue';
import { defineComponent, ref, watchEffect } from 'vue';
import { RangePicker } from 'ant-design-vue';
import type { DurationLike } from 'luxon';
import { DateTime } from 'luxon';
import type { Dayjs } from 'dayjs';
import dayjs from 'dayjs';

export default defineComponent({
    components: { RangePicker },
    props: {
        startTime: { type: Number, default: 0 },
        endTime: { type: Number, default: 0 },
        id: { type: Function as PropType<StringConstructor>, default: () => 'queryTimeRange' },
        timesAgo: { type: Object as PropType<DurationLike>, required: true }
    },
    emits: {
        'update:startTime': emitEventNumValidator,
        'update:endTime': emitEventNumValidator
    },
    setup(props, { emit }) {
        const timeRange = ref<[Dayjs, Dayjs]>([dayjs(), dayjs()]);
        const timeRangeChanged = ([startTime, endTime]: [Dayjs, Dayjs]) => {
            emit('update:startTime', startTime.unix());
            emit('update:endTime', endTime.unix());
        };

        watchEffect(() => {
            timeRange.value = [dayjs.unix(props.startTime), dayjs.unix(props.endTime)];
        });
        const initialRangeWithTimesAgo: [Dayjs, Dayjs] = [
            dayjs(DateTime.now().minus(props.timesAgo).startOf('minute').toISO()),
            dayjs(DateTime.now().startOf('minute').toISO())
        ];
        // timesAgo will overwrite first assign to timeRange with initial props value
        timeRange.value = initialRangeWithTimesAgo;
        timeRangeChanged(initialRangeWithTimesAgo);

        return { dayjs, timeRange, timeRangeChanged };
    }
});
</script>
