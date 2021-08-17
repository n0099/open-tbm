<template>
    <form @submit.prevent="submitQueryForm()" class="mt-3">
        <div class="row">
            <label class="col-2 col-form-label" for="queryStatusTime">时间范围</label>
            <div class="col-7">
                <div id="queryStatusTime" class="input-group">
                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                    <input v-model="statusQuery.startTime" type="datetime-local" class="form-control">
                    <span class="input-group-text">至</span>
                    <input v-model="statusQuery.endTime" type="datetime-local" class="form-control">
                </div>
            </div>
            <label class="col-1 col-form-label border-start text-center" for="queryStatusTimeRange">时间粒度</label>
            <div class="col-2">
                <div class="input-group">
                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                    <select v-model="statusQuery.timeRange" id="queryStatusTimeRange" class="custom-select form-control">
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
        <div ref="statusChartDom" class="echarts col mt-2"></div>
    </div>
</template>

<script lang="ts">
import { defineComponent, onMounted, reactive, ref, toRefs, watch } from 'vue';
import { apiStatus, isApiError } from '@/api';
import type { ApiQSStatus } from '@/api.d';
import { DateTime } from 'luxon';
import _ from 'lodash';
import type { ECharts } from 'echarts/core';
import * as echarts from 'echarts/core';
import { LineChart, LineSeriesOption } from 'echarts/charts';
import { AxisPointerComponent, AxisPointerComponentOption, DataZoomComponent, DataZoomComponentOption, GridComponent, GridComponentOption, LegendComponent, LegendComponentOption, TitleComponent, TitleComponentOption, ToolboxComponent, ToolboxComponentOption, TooltipComponent, TooltipComponentOption, VisualMapComponent, VisualMapComponentOption } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([CanvasRenderer, LineChart, AxisPointerComponent, DataZoomComponent, GridComponent, LegendComponent, TitleComponent, ToolboxComponent, TooltipComponent, VisualMapComponent]);
const chartInitialOption: echarts.ComposeOption<LineSeriesOption | AxisPointerComponentOption | DataZoomComponentOption | GridComponentOption | LegendComponentOption | TitleComponentOption | ToolboxComponentOption | TooltipComponentOption | VisualMapComponentOption> = {
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
        start: 90,
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
        gridIndex: 1,
        position: 'top'
    }],
    yAxis: [{
        type: 'value',
        splitArea: { show: true },
        splitLine: { show: false }
    }, {
        type: 'value',
        gridIndex: 1,
        inverse: true,
        splitArea: { show: true },
        splitLine: { show: false }
    }, { // 网络请求量下表副Y轴
        type: 'value',
        gridIndex: 1,
        splitLine: { show: false }
    }],
    series: [{
        id: 'queueTiming',
        name: '单位总耗时',
        type: 'line',
        step: 'middle',
        symbolSize: 2,
        sampling: 'average',
        markLine: {
            symbol: 'none',
            lineStyle: { type: 'dotted' },
            data: [{ yAxis: 30 }, { yAxis: 60 }, { yAxis: 120 }, { yAxis: 240 }, { yAxis: 480 }]
        }
    }, {
        id: 'savePostsTiming',
        name: '贴子保存耗时',
        type: 'line',
        symbolSize: 0,
        sampling: 'average',
        areaStyle: {},
        stack: 'queueTiming'
    }, {
        id: 'webRequestTiming',
        name: '网络请求耗时',
        type: 'line',
        symbolSize: 0,
        sampling: 'average',
        areaStyle: {},
        stack: 'queueTiming'
    }, {
        id: 'webRequestTimes',
        name: '网络请求量',
        xAxisIndex: 1,
        yAxisIndex: 2,
        type: 'line',
        symbolSize: 2,
        sampling: 'average'
    }, {
        id: 'parsedPostTimes',
        name: '处理贴子量',
        xAxisIndex: 1,
        yAxisIndex: 1,
        type: 'line',
        symbolSize: 2,
        sampling: 'average'
    }, {
        id: 'parsedUserTimes',
        name: '处理用户量',
        xAxisIndex: 1,
        yAxisIndex: 1,
        type: 'line',
        symbolSize: 2,
        sampling: 'average'
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
                timeRange: 'minute',
                startTime: DateTime.now().minus({ weeks: 1 }).startOf('second')
                    .toISO({ includeOffset: false }),
                endTime: DateTime.now().startOf('second')
                    .toISO({ includeOffset: false })
            } as ApiQSStatus
        });
        const submitQueryForm = () => {
            // fully refresh to regenerate a new echarts instance
            statusChart.value?.clear();
            if (statusChartDom.value === undefined) return;
            statusChart.value = echarts.init(statusChartDom.value);
            statusChartDom.value.classList.add('loading');
            statusChart.value.setOption(chartInitialOption);
            (async () => {
                const statusResult = await apiStatus(state.statusQuery);
                if (isApiError(statusResult)) return;
                const series = _.chain(chartInitialOption.series)
                    .map('id')
                    .map((seriesName: string) => ({
                        id: seriesName,
                        data: _.map(statusResult, i => [i.startTime, i[seriesName]]) // select column from status
                    }))
                    .value();
                statusChart?.value?.setOption({ series });
            })();
            statusChartDom.value.classList.remove('loading');
        };

        watch(() => state.autoRefresh, autoRefresh => {
            if (autoRefresh) autoRefreshIntervalID.value = window.setInterval(submitQueryForm, 100); // refresh data every minute
            else clearInterval(autoRefreshIntervalID.value);
        });
        onMounted(submitQueryForm);

        return { ...toRefs(state), statusChartDom };
    }
});
</script>

<style scoped>
#statusChartDom {
    height: 40em;
}
</style>
