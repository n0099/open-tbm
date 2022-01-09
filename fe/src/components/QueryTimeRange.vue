<template>
    <RangePicker @change="timeRangeChanged" :id="id" :value="timeRange" :ranges="{
        昨天: [moment().subtract(1, 'day').startOf('day'), moment().subtract(1, 'day').endOf('day')],
        今天: [moment().startOf('day'), moment().endOf('day')],
        本周: [moment().startOf('week'), moment().endOf('week')],
        最近7天: [moment().subtract(7, 'days'), moment()],
        本月: [moment().startOf('month'), moment().endOf('momth')],
        最近30天: [moment().subtract(30, 'days'), moment()]
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
import type { Moment } from 'moment';
import moment from 'moment';

export default defineComponent({
    components: { RangePicker },
    props: {
        startTime: { type: Number, default: 0 },
        endTime: { type: Number, default: 0 },
        id: { type: String, default: 'queryTimeRange' },
        timesAgo: { type: Object as PropType<DurationLike>, required: true }
    },
    emits: {
        'update:startTime': emitEventNumValidator,
        'update:endTime': emitEventNumValidator
    },
    setup(props, { emit }) {
        const timeRange = ref<Moment[]>([]);
        const timeRangeChanged = ([startTime, endTime]: Moment[]) => {
            emit('update:startTime', startTime.unix());
            emit('update:endTime', endTime.unix());
        };

        watchEffect(() => {
            timeRange.value = [moment.unix(props.startTime), moment.unix(props.endTime)];
        });
        // timesAgo will overwrite first assign to timeRange with initial props value
        timeRange.value = [
            moment(DateTime.now().minus(props.timesAgo).startOf('minute').toISO()),
            moment(DateTime.now().startOf('minute').toISO())
        ];

        return { moment, timeRange, timeRangeChanged };
    }
});
</script>
