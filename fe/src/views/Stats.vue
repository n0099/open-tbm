<template>
    <form @submit.prevent="submitQueryForm()" class="mt-3">
        <div class="row">
            <label class="col-2 col-form-label text-end" for="queryStatsForum">贴吧</label>
            <div class="col-3">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="filter" /></span>
                    <select v-model.number="query.fid" id="queryStatsForum" class="form-control">
                        <option disabled value="0">请选择</option>
                        <option v-for="forum in forumList" :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                    </select>
                </div>
            </div>
            <div class="col-4"></div>
            <button :disabled="query.fid === 0" type="submit" class="col-auto btn btn-primary">查询</button>
        </div>
        <div class="row mt-2">
            <label class="col-2 col-form-label text-end" for="queryStatusTime">时间范围</label>
            <div class="col-5">
                <div id="queryStatusTime" class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="calendar-alt" /></span>
                    <RangePicker v-model:value="timeRange" :ranges="{
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
                    <span class="input-group-text"><FontAwesomeIcon icon="clock" /></span>
                    <select v-model="query.timeGranular" id="queryStatusTimeRange" class="form-control">
                        <option value="minute">分钟</option>
                        <option value="hour">小时</option>
                        <option value="day">天</option>
                        <option value="week">周</option>
                        <option value="month">月</option>
                        <option value="year">年</option>
                    </select>
                </div>
            </div>
        </div>
    </form>
    <div class="row mt-2">
        <div ref="chartDom" id="statsChartDom" class="echarts col mt-2"></div>
    </div>
</template>

<script lang="ts">
import type { ApiForumList, ApiStatsQP } from '@/api/index.d';
import { apiForumList, apiStatsForumPostsCount, isApiError } from '@/api';
import { emptyChartSeriesData, extendCommonToolbox, timeGranularAxisPointerLabelFormatter, timeGranularAxisType } from '@/shared/echarts';

import _ from 'lodash';
import { defineComponent, onMounted, reactive, ref, toRefs, watch } from 'vue';
import { RangePicker } from 'ant-design-vue';
import { DateTime } from 'luxon';
import type { Moment } from 'moment';
import moment from 'moment';
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
        filterMode: 'filter',
        start: 0
    }, {
        type: 'inside', filterMode: 'filter'
    }],
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
    components: { FontAwesomeIcon, RangePicker },
    setup() {
        const chartDom = ref<HTMLElement>();
        const state = reactive<{
            query: ApiStatsQP,
            timeRange: Moment[],
            forumList: ApiForumList
        }>({
            query: {
                fid: 0,
                timeGranular: 'day',
                startTime: 0,
                endTime: 0
            },
            timeRange: [
                moment(DateTime.now().minus({ days: 30 }).startOf('minute').toISO()),
                moment(DateTime.now().startOf('minute').toISO())
            ],
            forumList: []
        });
        const submitQueryForm = () => {
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
            (async () => {
                const statsResult = await apiStatsForumPostsCount(state.query);
                if (isApiError(statsResult)) return;
                const series = _.map(statsResult, (dates, postType) => ({
                    id: postType,
                    data: _.map(dates, _.values)
                }));
                const axisType = timeGranularAxisType[state.query.timeGranular];
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
                        axisPointer: { label: { formatter: timeGranularAxisPointerLabelFormatter[state.query.timeGranular] } }
                    },
                    series
                });
            })().finally(() => { chartDom.value?.classList.remove('loading') });
        };

        watch(() => state.timeRange, timeRange => {
            [state.query.startTime, state.query.endTime] = timeRange.map(i => i.unix());
        }, { immediate: true });
        onMounted(async () => {
            const forumListResult = await apiForumList();
            if (isApiError(forumListResult)) return;
            state.forumList = forumListResult;
        });

        return { moment, ...toRefs(state), chartDom, submitQueryForm };
    }
});
</script>

<style scoped>
#statsChartDom {
    height: 25em;
}
</style>
