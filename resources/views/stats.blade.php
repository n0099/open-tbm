@extends('layout')
@include('module.echarts')

@section('title', '统计')

@section('style')
    <style>
        #statsChartDOM {
            height: 20em;
        }
    </style>
@endsection

@section('container')
    <form @submit.prevent="submitQueryForm()" id="statsForm" class="mt-3">
        <div class="form-group form-row">
            <label class="col-2 col-form-label" for="queryStatsForum">贴吧</label>
            <div class="col-4 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-filter"></i></span>
                </div>
                <select v-model.number="statsQuery.fid" id="queryStatsForum" class="custom-select form-control">
                    <option v-for="forum in forumsList" :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                </select>
            </div>
            <label class="col-2 col-form-label border-left text-center" for="queryStatsTimeRange">时间粒度</label>
            <div class="col-2 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                </div>
                <select v-model="statsQuery.timeRange" id="queryStatsTimeRange" class="custom-select form-control">
                    <option value="minute">分钟</option>
                    <option value="hour">小时</option>
                    <option value="day">天</option>
                    <option value="week">周</option>
                    <option value="month">月</option>
                    <option value="year">年</option>
                </select>
            </div>
        </div>
        <div class="form-group form-row">
            <label class="col-2 col-form-label" for="queryStatsTime">时间范围</label>
            <div id="queryStatsTime" class="col-8 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                </div>
                <input v-model="statsQuery.startTime" id="queryStatsStartTime" type="datetime-local" class="form-control">
                <div class="input-group-prepend input-group-append"><span class="input-group-text">至</span></div>
                <input v-model="statsQuery.endTime" id="queryStatsEndTime" type="datetime-local" class="form-control">
            </div>
            <button type="submit" class="ml-auto btn btn-primary">查询</button>
        </div>
    </form>
    <div id="statsChart" class="row mt-2">
        <div id="statsChartDOM" class="echarts col mt-2"></div>
    </div>
@endsection

@section('script')
    <script>
        'use strict';
        $$initialNavBar('stats');

        let statsChartDOM;
        let statsChart;
        const initialStatsChart = () => {
            statsChartDOM = $('#statsChartDOM');
            statsChart = echarts.init(statsChartDOM[0], 'light');
            statsChart.setOption({
                title: {
                    text: '吧贴量统计'
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'shadow' }
                },
                toolbox: {
                    feature: {
                        dataZoom: { show: true, yAxisIndex: 'none' },
                        restore: { show: true },
                        dataView: { show: true },
                        saveAsImage: { show: true },
                        magicType: { show: true, type: ['stack', 'tiled'] },
                    }
                },
                dataZoom: [
                    {
                        type: 'slider',
                        filterMode: 'filter',
                        start: 0,
                        bottom: 0
                    },
                    {
                        type: 'inside',
                        filterMode: 'filter'
                    }
                ],
                legend: {},
                xAxis: {
                    type: 'time'
                },
                yAxis: {
                    type: 'value',
                },
                series: [
                    {
                        id: 'thread',
                        name: '主题贴',
                        type: 'line',
                        symbolSize: 2,
                        smooth: true,
                        sampling: 'average',
                        stack: 'postsCount'
                    },
                    {
                        id: 'reply',
                        name: '回复贴',
                        type: 'line',
                        symbolSize: 2,
                        smooth: true,
                        sampling: 'average',
                        stack: 'postsCount'
                    },
                    {
                        id: 'subReply',
                        name: '楼中楼',
                        type: 'line',
                        symbolSize: 2,
                        smooth: true,
                        sampling: 'average',
                        stack: 'postsCount'
                    }
                ]
            });
        };
        const loadStatsChart = (statsQuery, forumsList) => {
            statsChartDOM.addClass('loading');
            $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                $.getJSON(`${$$baseUrl}/api/stats/forumPostsCount`, $.param(_.merge(statsQuery, reCAPTCHAToken)))
                    .done((ajaxData) => {
                        let series = _.map(ajaxData, (datas, postType) => {
                            return {
                                id: postType,
                                data: _.map(datas, _.values)
                            };
                        });
                        let timeCategories = _.chain(ajaxData)
                            .map((counts) => _.map(counts, (count) => count.time))
                            .flatten()
                            .sort()
                            .uniq()
                            .value();
                        statsChart.setOption({
                            title: {
                                text: `${_.find(forumsList, { fid: statsQuery.fid }).name}吧贴量统计`
                            },
                            xAxis: {
                                data: timeCategories,
                                type: $$echartsTimeRangeAxisType[statsQuery.timeRange],
                                axisPointer: {
                                    label: {
                                        formatter: $$echartsTimeRangeAxisPointerLabelFormatter[statsQuery.timeRange]
                                    }
                                }
                            },
                            series
                        });
                    })
                    .always(() => statsChartDOM.removeClass('loading'));
            });
        };

        const statsChartVue = new Vue({
            el: '#statsForm',
            data: {
                forumsList: [],
                statsQuery: {
                    timeRange: 'day',
                    startTime: moment().subtract(1, 'week').format('YYYY-MM-DDTHH:mm'),
                    endTime: moment().format('YYYY-MM-DDTHH:mm')
                }
            },
            mounted () {
                initialStatsChart();
                $$loadForumList().then((forumsList) => this.$data.forumsList = forumsList);
                new Noty({ timeout: 3000, type: 'info', text: '请选择贴吧或/并输入查询参数'}).show();
            },
            methods: {
                submitQueryForm () {
                    // fully refresh to regenerate a new echarts instance
                    statsChart.clear();
                    initialStatsChart();
                    loadStatsChart(this.$data.statsQuery, this.$data.forumsList);
                }
            }
        });
    </script>
@endsection
