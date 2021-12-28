<template>
    <form @submit.prevent="submitQueryForm()" class="mt-3">
        <div class="row">
            <label class="col-2 col-form-label text-end" for="queryFid">贴吧</label>
            <div class="col-3">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="filter" /></span>
                    <select v-model.number="query.fid" id="queryFid" class="form-control">
                        <option disabled value="0">请选择</option>
                        <option v-for="forum in forumList" :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                    </select>
                </div>
            </div>
            <div class="col-4"></div>
            <button :disabled="query.fid === 0" type="submit" class="col-auto btn btn-primary">查询</button>
        </div>
        <div class="row mt-2">
            <label class="col-2 col-form-label text-end" for="queryTimeRange">时间范围</label>
            <div class="col-5">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="calendar-alt" /></span>
                    <QueryTimeRange v-model:startTime="query.startTime" v-model:endTime="query.endTime" :timesAgo="{ day: 1 }" />
                </div>
            </div>
            <label class="col-1 col-form-label text-end" for="queryTimeGranularity">时间粒度</label>
            <div class="col-2">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="clock" /></span>
                    <QueryTimeGranularity v-model="query.timeGranularity" :granularities="timeGranularities" />
                </div>
            </div>
        </div>
    </form>
    <div ref="chartDom" id="statsChartDom" class="echarts mt-4"></div>
</template>

<script lang="ts">
import type { ApiForumList, ApiStatsForumPostsCountQP } from '@/api/index.d';
import { apiForumList, apiStatsForumPostsCount, throwIfApiError } from '@/api';
import { emptyChartSeriesData, extendCommonToolbox, timeGranularities, timeGranularityAxisPointerLabelFormatter, timeGranularityAxisType } from '@/shared/echarts';
import QueryTimeGranularity from '@/components/QueryTimeGranularity.vue';
import QueryTimeRange from '@/components/QueryTimeRange.vue';

import _ from 'lodash';
import { defineComponent, reactive, ref, toRefs } from 'vue';
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
    title: { text: '吧贴量统计' },
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
        name: '主题贴（右轴）',
        yAxisIndex: 1
    }, {
        ...commonSeriesOption,
        id: 'reply',
        name: '回复贴',
        stack: 'postsCount'
    }, {
        ...commonSeriesOption,
        id: 'subReply',
        name: '楼中楼',
        stack: 'postsCount'
    }]
};

export default defineComponent({
    components: { FontAwesomeIcon, QueryTimeGranularity, QueryTimeRange },
    setup() {
        const chartDom = ref<HTMLElement>();
        const state = reactive<{
            query: ApiStatsForumPostsCountQP,
            forumList: ApiForumList
        }>({
            query: {
                fid: 0,
                timeGranularity: 'day',
                startTime: 0,
                endTime: 0
            },
            forumList: []
        });
        const submitQueryForm = async () => {
            if (chartDom.value === undefined) return;
            chartDom.value.classList.add('loading');
            if (chart === null) {
                chart = echarts.init(chartDom.value);
                chart.setOption(chartInitialOption);
            }
            emptyChartSeriesData(chart);
            chart.setOption<echarts.ComposeOption<TitleComponentOption>>(
                { title: { text: `${_.find(state.forumList, { fid: state.query.fid })?.name}吧贴量统计` } }
            );

            const statsResult = throwIfApiError(await apiStatsForumPostsCount(state.query)
                .finally(() => { chartDom.value?.classList.remove('loading') }));
            const series = _.map(statsResult, (dates, postType) => ({
                id: postType,
                data: _.map(dates, Object.values)
            }));
            const axisType = timeGranularityAxisType[state.query.timeGranularity];
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
                    axisPointer: { label: { formatter: timeGranularityAxisPointerLabelFormatter[state.query.timeGranularity] } }
                },
                series
            });
        };

        (async () => {
            state.forumList = throwIfApiError(await apiForumList());
        })();

        return { timeGranularities, ...toRefs(state), chartDom, submitQueryForm };
    }
});
</script>

<style scoped>
#statsChartDom {
    height: 25rem;
}
</style>
