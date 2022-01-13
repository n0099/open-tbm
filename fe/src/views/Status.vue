<template>
    <form @submit.prevent="submitQueryForm" class="mt-3">
        <div class="row">
            <label class="col-3 col-form-label text-end" for="queryTimeRange">时间范围</label>
            <div class="col-5">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="calendar-alt" /></span>
                    <QueryTimeRange v-model:startTime="query.startTime" v-model:endTime="query.endTime" :timesAgo="{ day: 30 }" />
                </div>
            </div>
            <label class="col-1 col-form-label text-end" for="queryTimeGranularity">时间粒度</label>
            <div class="col-2">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="clock" /></span>
                    <QueryTimeGranularity v-model="query.timeGranularity" :granularities="['minute', 'hour', 'day']" />
                </div>
            </div>
        </div>
        <div class="row justify-content-end mt-1">
            <div class="col-auto my-auto">
                <span><Switch v-model:checked="autoRefresh" /></span>
                <span class="ms-1">每分钟自动刷新</span>
            </div>
            <button type="submit" class="col-auto btn btn-primary">查询</button>
        </div>
    </form>
    <div ref="chartDom" id="statusChartDom" class="echarts mt-4" />
</template>

<script lang="ts">
import type { ApiStatus, ApiStatusQP } from '@/api/index.d';
import { apiStatus, throwIfApiError } from '@/api';
import { commonToolboxFeatures, emptyChartSeriesData } from '@/shared/echarts';
import QueryTimeGranularity from '@/components/QueryTimeGranularity.vue';
import QueryTimeRange from '@/components/QueryTimeRange.vue';

import { defineComponent, onMounted, reactive, ref, toRefs, watch } from 'vue';
import { useIntervalFn } from '@vueuse/core';
import { Switch } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';

import * as echarts from 'echarts/core';
import type { LineSeriesOption } from 'echarts/charts';
import { LineChart } from 'echarts/charts';
import { CanvasRenderer } from 'echarts/renderers';
import { UniversalTransition } from 'echarts/features';
import type { DataZoomComponentOption, GridComponentOption, LegendComponentOption, MarkLineComponentOption, TitleComponentOption, ToolboxComponentOption, TooltipComponentOption, VisualMapComponentOption } from 'echarts/components';
import { DataZoomComponent, GridComponent, LegendComponent, MarkLineComponent, TitleComponent, ToolboxComponent, TooltipComponent, VisualMapComponent } from 'echarts/components';

echarts.use([TitleComponent, ToolboxComponent, TooltipComponent, GridComponent, VisualMapComponent, LegendComponent, DataZoomComponent, MarkLineComponent, LineChart, CanvasRenderer, UniversalTransition]);
let chart: echarts.ECharts | null = null;
const commonSeriesOption: LineSeriesOption = {
    type: 'line',
    symbolSize: 0,
    sampling: 'lttb',
    universalTransition: true
};
const chartInitialOption: echarts.ComposeOption<DataZoomComponentOption | GridComponentOption | LegendComponentOption | LineSeriesOption | MarkLineComponentOption | TitleComponentOption | ToolboxComponentOption | TooltipComponentOption | VisualMapComponentOption> = {
    title: { text: '近期性能统计' },
    tooltip: { trigger: 'axis' },
    axisPointer: { link: [{ xAxisIndex: 'all' }] },
    ...commonToolboxFeatures,
    dataZoom: [{
        type: 'slider',
        xAxisIndex: [0, 1],
        start: 50,
        bottom: '46%'
    }, {
        type: 'inside',
        xAxisIndex: [0, 1]
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
        ...commonSeriesOption,
        id: 'queueTiming',
        name: '单位总耗时',
        xAxisIndex: 0,
        yAxisIndex: 0,
        step: 'middle',
        symbolSize: 2,
        markLine: {
            symbol: 'none',
            lineStyle: { type: 'dotted' },
            data: [{ yAxis: 30 }, { yAxis: 60 }, { yAxis: 120 }, { yAxis: 240 }, { yAxis: 480 }]
        }
    }, {
        ...commonSeriesOption,
        id: 'savePostsTiming',
        name: '帖子保存耗时',
        xAxisIndex: 0,
        yAxisIndex: 0,
        stack: 'queueTotalTiming'
    }, {
        ...commonSeriesOption,
        id: 'webRequestTiming',
        name: '网络请求耗时',
        xAxisIndex: 0,
        yAxisIndex: 0,
        stack: 'queueTotalTiming'
    }, {
        ...commonSeriesOption,
        id: 'webRequestTimes',
        name: '网络请求量',
        xAxisIndex: 1,
        yAxisIndex: 1
    }, {
        ...commonSeriesOption,
        id: 'parsedPostTimes',
        name: '处理帖子量（右轴）',
        xAxisIndex: 1,
        yAxisIndex: 2
    }, {
        ...commonSeriesOption,
        id: 'parsedUserTimes',
        name: '处理用户量',
        xAxisIndex: 1,
        yAxisIndex: 1
    }]
};

export default defineComponent({
    components: { FontAwesomeIcon, Switch, QueryTimeGranularity, QueryTimeRange },
    setup() {
        const chartDom = ref<HTMLElement>();
        const state = reactive<{
            autoRefresh: boolean,
            query: ApiStatusQP
        }>({
            autoRefresh: false,
            query: {
                timeGranularity: 'minute',
                startTime: 0,
                endTime: 0
            }
        });
        const submitQueryForm = async () => {
            if (chartDom.value === undefined) return;
            chartDom.value.classList.add('loading');
            if (chart === null) {
                chart = echarts.init(chartDom.value);
                chart.setOption(chartInitialOption);
            }
            emptyChartSeriesData(chart);

            const statusResult = throwIfApiError(await apiStatus(state.query)
                .finally(() => { chartDom.value?.classList.remove('loading') }));
            const series = _.chain(chartInitialOption.series)
                .map('id')
                .map((seriesName: keyof ApiStatus[0]) => ({
                    id: seriesName,
                    // select column from status, UnixTimestamp * 1000 since echarts only accepts milliseconds
                    data: statusResult.map(i => [i.startTime * 1000, i[seriesName]])
                }))
                .value();
            chart.setOption<echarts.ComposeOption<LineSeriesOption>>({ series });
        };
        const { pause, resume } = useIntervalFn(submitQueryForm, 60000, { immediate: false }); // refresh data every minute

        watch(() => state.autoRefresh, autoRefresh => {
            if (autoRefresh) resume();
            else pause();
        });
        onMounted(submitQueryForm);

        return { ...toRefs(state), chartDom, submitQueryForm };
    }
});
</script>

<style scoped>
#statusChartDom {
    height: 40rem;
}
</style>
