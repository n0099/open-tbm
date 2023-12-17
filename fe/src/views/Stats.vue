<template>
    <form @submit.prevent="submitQueryForm" class="mt-3">
        <div class="row">
            <label class="col-2 col-form-label text-end" for="queryFid">贴吧</label>
            <div class="col-3">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="filter" /></span>
                    <select v-model.number="query.fid" class="form-control" id="queryFid">
                        <option disabled value="0">请选择</option>
                        <option v-for="forum in forumList"
                                :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                    </select>
                </div>
            </div>
            <div class="col-4" />
            <button :disabled="query.fid === 0" type="submit" class="col-auto btn btn-primary">查询</button>
        </div>
        <div class="row mt-2">
            <label class="col-2 col-form-label text-end" for="queryTimeRange">时间范围</label>
            <div class="col-5">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="calendar-alt" /></span>
                    <TimeRange v-model:startTime="query.startTime"
                               v-model:endTime="query.endTime"
                               :timesAgo="{ day: 14 }" id="queryTimeRange" />
                </div>
            </div>
            <label class="col-1 col-form-label text-end" for="queryTimeGranularity">时间粒度</label>
            <div class="col-2">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="clock" /></span>
                    <TimeGranularity v-model="query.timeGranularity"
                                     :granularities="timeGranularities as Writable<typeof timeGranularities>"
                                     id="queryTimeGranularity" />
                </div>
            </div>
        </div>
    </form>
    <div ref="chartDom" class="echarts mt-4" id="statsChartDom" />
</template>

<script setup lang="ts">
import TimeGranularity from '@/components/widgets/TimeGranularity.vue';
import TimeRange from '@/components/widgets/TimeRange.vue';

import type { ApiForumList, ApiStatsForumPostCountQueryParam } from '@/api/index.d';
import { apiForumList, apiStatsForumsPostCount, throwIfApiError } from '@/api';
import { emptyChartSeriesData, extendCommonToolbox, timeGranularities, timeGranularityAxisPointerLabelFormatter, timeGranularityAxisType } from '@/shared/echarts';
import { titleTemplate } from '@/shared';
import type { Writable } from '@/shared';

import _ from 'lodash';
import { ref } from 'vue';
import { useHead } from '@unhead/vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

import * as echarts from 'echarts/core';
import type { DataZoomComponentOption, GridComponentOption, LegendComponentOption, TitleComponentOption, ToolboxComponentOption, TooltipComponentOption } from 'echarts/components';
import { DataZoomComponent, GridComponent, LegendComponent, TitleComponent, ToolboxComponent, TooltipComponent } from 'echarts/components';
import type { LineSeriesOption } from 'echarts/charts';
import { BarChart, LineChart } from 'echarts/charts';
import { UniversalTransition } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([TitleComponent, ToolboxComponent, TooltipComponent, GridComponent, LegendComponent, DataZoomComponent, LineChart, BarChart, CanvasRenderer, UniversalTransition]);
let chart: echarts.ECharts | null = null;
const commonSeriesOption: LineSeriesOption = {
    type: 'line',
    symbolSize: 1,
    smooth: true,
    sampling: 'lttb',
    universalTransition: true
};
const chartInitialOption: echarts.ComposeOption<DataZoomComponentOption | GridComponentOption | LegendComponentOption | LineSeriesOption | TitleComponentOption | ToolboxComponentOption | TooltipComponentOption> = {
    title: { text: '吧帖量统计' },
    axisPointer: { type: 'shadow' },
    tooltip: { trigger: 'axis' },
    ...extendCommonToolbox({
        toolbox: {
            feature: {
                magicType: { show: true, type: ['stack', 'line', 'bar'] }
            }
        }
    }),
    dataZoom: [{
        type: 'slider',
        start: 0
    }, { type: 'inside' }],
    legend: {},
    xAxis: { type: 'time' },
    yAxis: [
        { type: 'value' },
        { type: 'value', splitLine: { show: false } }
    ],
    series: [{
        ...commonSeriesOption,
        id: 'thread',
        name: '主题帖（右轴）',
        yAxisIndex: 1
    }, {
        ...commonSeriesOption,
        id: 'reply',
        name: '回复帖',
        stack: 'postCount'
    }, {
        ...commonSeriesOption,
        id: 'subReply',
        name: '楼中楼',
        stack: 'postCount'
    }]
};

useHead({ title: titleTemplate('统计') });
const query = ref<ApiStatsForumPostCountQueryParam>({
    fid: 0,
    timeGranularity: 'day',
    startTime: 0,
    endTime: 0
});
const forumList = ref<ApiForumList>([]);
const chartDom = ref<HTMLElement>();

const submitQueryForm = async () => {
    if (chartDom.value === undefined)
        return;
    chartDom.value.classList.add('loading');
    if (chart === null) {
        chart = echarts.init(chartDom.value);
        chart.setOption(chartInitialOption);
    }
    emptyChartSeriesData(chart);
    chart.setOption<echarts.ComposeOption<TitleComponentOption>>(
        { title: { text: `${_.find(forumList.value, { fid: query.value.fid })?.name}吧帖量统计` } }
    );

    const statsResult = throwIfApiError(await apiStatsForumsPostCount(query.value)
        .finally(() => { chartDom.value?.classList.remove('loading') }));
    const series = _.map(statsResult, (dates, postType) => ({
        id: postType,
        data: _.map(dates, Object.values)
    }));
    const axisType = timeGranularityAxisType[query.value.timeGranularity];
    chart.setOption<echarts.ComposeOption<GridComponentOption | LineSeriesOption>>({
        dataZoom: [{ start: 0, end: 100 }],
        xAxis: {
            ...axisType === 'category'
                ? {
                    data: _.chain(statsResult)
                        .map(counts => _.map(counts, 'time'))
                        .flatten()
                        .sort()
                        .sortedUniq()
                        .value()
                }
                : {},
            type: axisType,
            axisPointer: { label: { formatter: timeGranularityAxisPointerLabelFormatter[query.value.timeGranularity] } }
        },
        series
    });
};

(async () => {
    forumList.value = throwIfApiError(await apiForumList());
})();
</script>

<style scoped>
#statsChartDom {
    height: 25rem;
}
</style>
