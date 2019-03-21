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
                    <span class="input-group-text"><i class="fas fa-filter"></i></span>
                </div>
                <select v-model="statsQuery.fid" id="queryStatsForum" class="form-control">
                    <option v-for="forum in forumsList" v-text="forum.name" :value="forum.fid"></option>
                </select>
            </div>
            <label class="border-left text-center col-2 col-form-label" for="queryStatsTimeRange">时间粒度</label>
            <div class="col-2 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                </div>
                <select v-model="statsQuery.timeRange" id="queryStatsTimeRange" class="form-control">
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
            <div id="queryStatsTime" class="col-6 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                </div>
                <input v-model="statsQuery.startTime" id="queryStatsStartTime" type="datetime-local" class="form-control">
                <div class="input-group-prepend">
                    <span class="input-group-text">至</span>
                </div>
                <input v-model="statsQuery.endTime" id="queryStatsEndTime" type="datetime-local" class="form-control">
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
            created: function () {
                $$loadForumsList.then((forumsList) => {
                    this.$data.forumsList = forumsList;
                });
                new Noty({ timeout: 3000, type: 'info', text: '请选择贴吧或/并输入查询参数'}).show();
            },
            watch: {
                statsQuery: function (statsQuery) {
                    this.$data.submitDisabled = _.difference(_.keys(statsQuery), ['fid', 'timeRange', 'startTime', 'endTime']).length !== 0
                }
            },
            methods: {
                submitQueryForm: function () {
                    statsChartDOM.addClass('loading');
                    $$reCAPTCHACheck().then((token) => {
                        $.getJSON(`${$$baseUrl}/api/stats/forumPostsCount`, $.param(_.merge(this.$data.statsQuery, token))).done((jsonData) => {
                            let series = [];
                            _.each(jsonData, (datas, postType) => {
                                series.push({
                                    id: postType,
                                    data: _.map(datas, _.values)
                                });
                            });
                            statsChart.setOption({
                                title: {
                                    text: `${_.find(this.$data.forumsList, { fid: this.$data.statsQuery.fid }).name}吧贴量统计`
                                },
                                series
                            });
                            statsChartDOM.removeClass('loading');
                        }).fail($$apiErrorInfoParse);
                    });
                }
            }
        });

        let statsChartDOM = $('#statsChartDOM');
        let statsChart = echarts.init(statsChartDOM[0], 'light');
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
                    xAxisIndex: 0,
                    filterMode: 'filter',
                    start: 90,
                    bottom: 0
                },
                {
                    type: 'inside',
                    xAxisIndex: 0,
                    filterMode: 'filter'
                }
            ],
            legend: {
                data: ['主题贴', '回复贴', '楼中楼']
            },
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
                    label: {
                        show: true,
                        position: 'top'
                    },
                    stack: 'postsCount'
                }
            ]
        });
    </script>
@endsection