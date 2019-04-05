@extends('layout')

@section('title', '统计')

@section('container')
    <style>
        #statsChartDOM {
            height: 20em;
        }
    </style>
    <form @submit.prevent="submitQueryForm()" id="statsForm" class="mt-3 query-form">
        <div class="form-group form-row">
            <label class="col-2 col-form-label" for="queryStatsForum">贴吧</label>
            <div class="col-4 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text">
                        <i class="fas fa-filter"></i>
                    </span>
                </div>
                <select v-model="statsQuery.fid" id="queryStatsForum" class="form-control">
                    <option v-for="forum in forumsList" :key="forum.fid"
                            v-text="forum.name" :value="forum.fid"></option>
                </select>
            </div>
            <label class="border-left text-center col-2 col-form-label" for="queryStatsTimeRange">时间粒度</label>
            <div class="col-2 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text">
                        <i class="far fa-clock"></i>
                    </span>
                </div>
                <select v-model="statsQuery.timeRange"
                        id="queryStatsTimeRange" class="form-control">
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
                    <span class="input-group-text">
                        <i class="far fa-calendar-alt"></i>
                    </span>
                </div>
                <input v-model="statsQuery.startTime"
                       id="queryStatsStartTime" type="datetime-local" class="form-control">
                <div class="input-group-prepend">
                    <span class="input-group-text">至</span>
                </div>
                <input v-model="statsQuery.endTime"
                       id="queryStatsEndTime" type="datetime-local" class="form-control">
            </div>
            <button type="submit" :disabled="submitDisabled" class="ml-auto btn btn-primary">查询</button>
        </div>
    </form>
    <div id="statsChart" class="row mt-2">
        <div id="statsChartDOM" class="echarts col mt-2"></div>
    </div>
@endsection

@section('script-after-container')
    <script>
        'use strict';
        $$initialNavBar('stats');

        let statsChartDOM;
        let statsChart;
        let initialStatsChart = () => {
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
                        start: 90,
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
        let loadStatsChart = (statsQuery, forumsList) => {
            statsChartDOM.addClass('loading');
            $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                $.getJSON(`${$$baseUrl}/api/stats/forumPostsCount`, $.param(_.merge(statsQuery, reCAPTCHAToken)))
                    .done((jsonData) => {
                        let series = [];
                        _.each(jsonData, (datas, postType) => {
                            series.push({
                                id: postType,
                                data: _.map(datas, _.values)
                            });
                        });
                        statsChart.setOption({
                            title: {
                                text: `${_.find(forumsList, { fid: statsQuery.fid }).name}吧贴量统计`
                            },
                            series
                        });
                    })
                    .always(() => statsChartDOM.removeClass('loading'));
            });
        };

        let statsChartVue = new Vue({
            el: '#statsForm',
            data: {
                forumsList: [],
                statsQuery: {
                    timeRange: 'day',
                    startTime: moment().subtract(1, 'week').format('YYYY-MM-DDTHH:mm'),
                    endTime: moment().format('YYYY-MM-DDTHH:mm')
                },
                submitDisabled: true
            },
            watch: {
                statsQuery: function (statsQuery) {
                    this.$data.submitDisabled = _.difference(_.keys(statsQuery), ['fid', 'timeRange', 'startTime', 'endTime']).length !== 0
                }
            },
            methods: {
                submitQueryForm: function () {
                    // fully refresh to regenerate a new echarts instance
                    statsChart.clear();
                    initialStatsChart();
                    statsChartDOM.addClass('loading');
                    loadStatsChart(this.$data.statsQuery, this.$data.forumsList);
                }
            },
            mounted: function () {
                initialStatsChart();
                $$loadForumsList().then((forumsList) => {
                    this.$data.forumsList = forumsList;
                });
                new Noty({ timeout: 3000, type: 'info', text: '请选择贴吧或/并输入查询参数'}).show();
            }
        });
    </script>
@endsection