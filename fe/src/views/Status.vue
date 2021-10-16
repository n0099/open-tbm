<template>
    <form @submit.prevent="submitQueryForm()" class="mt-3">
        <div class="row">
            <label class="col-3 col-form-label text-end" for="queryStatusTime">时间范围</label>
            <div class="col-5">
                <div id="queryStatusTime" class="input-group">
                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                    <ARangePicker v-model:value="timeRange" :ranges="{
                        昨天: [moment().subtract(1, 'day').startOf('day'), moment().subtract(1, 'day').endOf('day')],
                        今天: [moment().startOf('day'), moment().endOf('day')],
                        本周: [moment().startOf('week'), moment().endOf('week')],
                        最近7天: [moment().subtract(7, 'days'), moment()],
                        本月: [moment().startOf('month'), moment().endOf('momth')],
                        最近30天: [moment().subtract(30, 'days'), moment()]
                    }" :format="'YYYY-MM-DD HH:mm'" :showTime="{
                        format: 'HH:mm',
                        minuteStep: 5,
                        secondStep: 10
                    }" :allowClear="false" size="large" class="col" />
                </div>
            </div>
            <label class="col-1 col-form-label text-end" for="queryStatusTimeRange">时间粒度</label>
            <div class="col-2">
                <div class="input-group">
                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                    <select v-model="statusQuery.timeGranular" id="queryStatusTimeRange" class="form-control">
                        <option value="minute">分钟</option>
                        <option value="hour">小时</option>
                        <option value="day">天</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row justify-content-end mt-1">
            <div class="col-auto my-auto">
                <span><ASwitch v-model:checked="autoRefresh" /></span>
                <span class="ms-1">每分钟自动刷新</span>
            </div>
            <button type="submit" class="col-auto btn btn-primary">查询</button>
        </div>
    </form>
    <div class="row mt-2">
        <div ref="statusChartDom" id="statusChartDom" class="echarts col mt-2"></div>
    </div>
</template>

<script lang="ts">
import { apiStatus, isApiError } from '@/api';
import type { ApiQSStatus, ApiStatus } from '@/api.d';

import { defineComponent, markRaw, onMounted, reactive, ref, toRefs, watch } from 'vue';
import { RangePicker, Switch } from 'ant-design-vue';
import type { ECharts } from 'echarts';
import * as echarts from 'echarts/core';
import type { LineSeriesOption } from 'echarts/charts';
import { LineChart } from 'echarts/charts';
import { CanvasRenderer } from 'echarts/renderers';
import { UniversalTransition } from 'echarts/features';
import type { DataZoomComponentOption, GridComponentOption, LegendComponentOption, MarkLineComponentOption, TitleComponentOption, ToolboxComponentOption, TooltipComponentOption, VisualMapComponentOption } from 'echarts/components';
import { DataZoomComponent, GridComponent, LegendComponent, MarkLineComponent, TitleComponent, ToolboxComponent, TooltipComponent, VisualMapComponent } from 'echarts/components';
import type { Moment } from 'moment';
import moment from 'moment';
import { DateTime } from 'luxon';
import _ from 'lodash';

echarts.use([TitleComponent, ToolboxComponent, TooltipComponent, GridComponent, VisualMapComponent, LegendComponent, DataZoomComponent, MarkLineComponent, LineChart, CanvasRenderer, UniversalTransition]);
const chartInitialOption: echarts.ComposeOption<DataZoomComponentOption | GridComponentOption | LegendComponentOption | LineSeriesOption | MarkLineComponentOption | TitleComponentOption | ToolboxComponentOption | TooltipComponentOption | VisualMapComponentOption> = {
    title: { text: '近期性能统计' },
    tooltip: { trigger: 'axis' },
    axisPointer: { link: [{ xAxisIndex: 'all' }] },
    toolbox: {
        feature: {
            dataZoom: { show: true, yAxisIndex: 'none' },
            restore: { show: true },
            dataView: { show: true },
            saveAsImage: { show: true }
        }
    },
    dataZoom: [{
        type: 'slider',
        xAxisIndex: [0, 1],
        filterMode: 'filter',
        start: 50,
        bottom: '46%'
    }, {
        type: 'inside',
        xAxisIndex: [0, 1],
        filterMode: 'filter'
    }],
    visualMap: [{
        seriesIndex: 0,
        top: 30,
        right: 0,
        pieces: [
            { gt: 0, lte: 30, color: '#096' },
            { gt: 30, lte: 60, color: '#ffde33' },
            { gt: 60, lte: 120, color: '#ff9933' },
            { gt: 120, lte: 240, color: '#cc0033' },
            { gt: 240, lte: 480, color: '#660099' },
            { gt: 480, color: '#7e0023' }
        ],
        outOfRange: { color: '#999' }
    }],
    legend: {},
    grid: [
        { height: '35%' },
        { height: '35%', top: '60%' }
    ],
    xAxis: [{
        type: 'time'
    }, {
        type: 'time',
        show: false,
        gridIndex: 1,
        position: 'top'
    }],
    yAxis: [{
        type: 'value',
        splitLine: { show: false }
    }, {
        type: 'value',
        gridIndex: 1,
        splitArea: { show: true },
        splitLine: { show: false }
    }, {
        type: 'value',
        gridIndex: 1,
        splitArea: { show: false },
        splitLine: { show: false }
    }],
    series: [{
        id: 'queueTiming',
        name: '单位总耗时',
        xAxisIndex: 0,
        yAxisIndex: 0,
        type: 'line',
        step: 'middle',
        symbolSize: 2,
        sampling: 'lttb',
        universalTransition: true,
        markLine: {
            symbol: 'none',
            lineStyle: { type: 'dotted' },
            data: [{ yAxis: 30 }, { yAxis: 60 }, { yAxis: 120 }, { yAxis: 240 }, { yAxis: 480 }]
        }
    }, {
        id: 'savePostsTiming',
        name: '贴子保存耗时',
        xAxisIndex: 0,
        yAxisIndex: 0,
        type: 'line',
        symbolSize: 0,
        sampling: 'lttb',
        universalTransition: true,
        stack: 'queueTotalTiming'
    }, {
        id: 'webRequestTiming',
        name: '网络请求耗时',
        xAxisIndex: 0,
        yAxisIndex: 0,
        type: 'line',
        symbolSize: 0,
        sampling: 'lttb',
        universalTransition: true,
        stack: 'queueTotalTiming'
    }, {
        id: 'webRequestTimes',
        name: '网络请求量',
        xAxisIndex: 1,
        yAxisIndex: 1,
        type: 'line',
        symbolSize: 0,
        sampling: 'lttb',
        universalTransition: true
    }, {
        id: 'parsedPostTimes',
        name: '处理贴子量（右轴）',
        xAxisIndex: 1,
        yAxisIndex: 2,
        type: 'line',
        symbolSize: 0,
        sampling: 'lttb',
        universalTransition: true
    }, {
        id: 'parsedUserTimes',
        name: '处理用户量',
        xAxisIndex: 1,
        yAxisIndex: 1,
        type: 'line',
        symbolSize: 0,
        sampling: 'lttb',
        universalTransition: true
    }]
};

export default defineComponent({
    setup() {
        const statusChartDom = ref<HTMLElement>();
        const statusChart = ref<ECharts>();
        const autoRefreshIntervalID = ref(0);
        const state = reactive({
            autoRefresh: false,
            statusQuery: {
                timeGranular: 'minute', // timeGranular
                startTime: 0,
                endTime: 0
            } as ApiQSStatus,
            timeRange: [
                moment(DateTime.now().minus({ days: 1 }).startOf('minute').toISO()),
                moment(DateTime.now().startOf('minute').toISO())
            ] as Moment[]
        });
        const submitQueryForm = () => {
            if (statusChartDom.value === undefined) return;
            statusChartDom.value.classList.add('loading');
            statusChart.value ??= markRaw(echarts.init(statusChartDom.value));
            statusChart.value.clear();
            statusChart.value.setOption(chartInitialOption);
            (async () => {
                const statusResult = await apiStatus(state.statusQuery);
                if (isApiError(statusResult)) return;
                const series = _.chain(chartInitialOption.series)
                    .map('id')
                    .map((seriesName: keyof ApiStatus[0]) => ({
                        id: seriesName,
                        data: _.map(statusResult, i => [i.startTime, i[seriesName]]) // select column from status
                    }))
                    .value();
                statusChart.value?.setOption({ series });
                statusChartDom.value?.classList.remove('loading');
            })();
        };

        watch(() => state.autoRefresh, autoRefresh => {
            if (autoRefresh) autoRefreshIntervalID.value = window.setInterval(submitQueryForm, 60000); // refresh data every minute
            else clearInterval(autoRefreshIntervalID.value);
        });
        watch(() => state.timeRange, timeRange => {
            [state.statusQuery.startTime, state.statusQuery.endTime] = timeRange.map(i => i.unix());
        }, { immediate: true });
        onMounted(submitQueryForm);

        return { RangePicker, Switch, moment, ...toRefs(state), statusChartDom, submitQueryForm };
    }
});
</script>

<style scoped>
#statusChartDom {
    height: 40em;
}
</style>
