@extends('layout')

@section('title', '状态')

@section('container')
    <style>
        #statusChartDOM {
            height: 40em;
        }
    </style>
    <div id="statusChart" class="row justify-content-end mt-2">
        <span><a-switch v-model="autoRefresh" /></span>
        <span> 每分钟自动刷新</span>
        <div class="w-100"></div>
        <div id="statusChartDOM" class="echarts loading col mt-2"></div>
    </div>
@endsection

@section('script-after-container')
    <script>
        'use strict';
        $$initialNavBar('status');

        let statusChartDOM;
        let statusChart;
        let initialStatusChart = () => {
            statusChartDOM = $('#statusChartDOM');
            statusChart = echarts.init(statusChartDOM[0]);
            statusChart.setOption({
                title: {
                    text: '近期性能统计'
                },
                tooltip: {
                    trigger: 'axis',
                },
                axisPointer: {
                    link: { xAxisIndex: 'all' }
                },
                toolbox: {
                    feature: {
                        dataZoom: { show: true, yAxisIndex: 'none' },
                        restore: { show: true },
                        dataView: { show: true },
                        saveAsImage: { show: true }
                    }
                },
                dataZoom: [
                    {
                        type: 'slider',
                        xAxisIndex: [0, 1],
                        filterMode: 'filter',
                        start: 90,
                        bottom: '46%'
                    },
                    {
                        type: 'inside',
                        xAxisIndex: [0, 1],
                        filterMode: 'filter'
                    }
                ],
                visualMap: [
                    {
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
                    }
                ],
                legend: {},
                grid: [
                    { height: '35%' },
                    { height: '35%', top: '60%' }
                ],
                xAxis: [
                    {
                        type: 'time'
                    },
                    {
                        type: 'time',
                        gridIndex: 1,
                        position: 'top'
                    }
                ],
                yAxis: [
                    {
                        type: 'value',
                        splitArea: { show: true },
                        splitLine: { show: false }
                    },
                    {
                        type: 'value',
                        gridIndex: 1,
                        inverse: true,
                        splitArea: { show: true },
                        splitLine: { show: false }
                    },
                    { // 网络请求量下表副Y轴
                        type: 'value',
                        gridIndex: 1,
                        splitLine: { show: false }
                    }
                ],
                series: [
                    {
                        id: 'duration',
                        name: '耗时',
                        type: 'line',
                        step: 'middle',
                        symbolSize: 2,
                        sampling: 'average',
                        areaStyle: {},
                        hoverAnimation: false,
                        markLine: {
                            symbol: 'none',
                            lineStyle: { type: 'dotted' },
                            data: [
                                { yAxis: 30 },
                                { yAxis: 60 },
                                { yAxis: 120 },
                                { yAxis: 240 },
                                { yAxis: 480 }
                            ]
                        }
                    },
                    {
                        id: 'webRequestTimes',
                        name: '网络请求量',
                        xAxisIndex: 1,
                        yAxisIndex: 2,
                        type: 'line',
                        symbolSize: 2,
                        sampling: 'average'
                    },
                    {
                        id: 'parsedPostTimes',
                        name: '处理贴子量',
                        xAxisIndex: 1,
                        yAxisIndex: 1,
                        type: 'line',
                        symbolSize: 2,
                        sampling: 'average',
                        areaStyle: {}
                    },
                    {
                        id: 'parsedUserTimes',
                        name: '处理用户量',
                        xAxisIndex: 1,
                        yAxisIndex: 1,
                        type: 'line',
                        symbolSize: 2,
                        sampling: 'average',
                        areaStyle: {}
                    }
                ]
            });
        };
        let loadStatusChart = () => {
            $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                $.getJSON(`${$$baseUrl}/api/status`, reCAPTCHAToken)
                    .done(function (jsonData) {
                        let selectColumnFromStatus = (prop) => {
                            return _.map(jsonData, (i) => {
                                return [
                                    i.startTime,
                                    Reflect.get(i, prop)
                                ];
                            });
                        };
                        statusChart.setOption({
                            series: [
                                {
                                    id: 'duration',
                                    data: selectColumnFromStatus('duration')
                                },
                                {
                                    id: 'webRequestTimes',
                                    data: selectColumnFromStatus('webRequestTimes')
                                },
                                {
                                    id: 'parsedPostTimes',
                                    data: selectColumnFromStatus('parsedPostTimes')
                                },
                                {
                                    id: 'parsedUserTimes',
                                    data: selectColumnFromStatus('parsedUserTimes')
                                }
                            ]
                        });
                    })
                    .fail($$apiErrorInfoParse)
                    .always(() => statusChartDOM.removeClass('loading'));
            });
        };

        let statusChartVue = new Vue({
            el: '#statusChart',
            data: {
                autoRefresh: false,
                autoRefreshIntervalID: 0
            },
            watch: {
                autoRefresh: function (autoRefresh) {
                    if (autoRefresh) {
                        this.$data.autoRefreshIntervalID = setInterval(() => {
                            loadStatusChart();
                        }, 60000); // refresh data every minute
                    } else {
                        clearInterval(this.$data.autoRefreshIntervalID);
                    }
                }
            },
            mounted: function () {
                initialStatusChart();
                loadStatusChart();
            }
        });
    </script>
@endsection