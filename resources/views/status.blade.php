@extends('layout')

@section('title', '状态')

@section('container')
    <style>
        #statusChartDOM {
            height: 32em;
        }
    </style>
    <div id="statusChart" class="row justify-content-end mt-2">
        <div class="col-3 custom-checkbox custom-control">
            <input v-model="autoRefresh" id="chkAutoRefresh" type="checkbox" class="custom-control-input">
            <label class="custom-control-label" for="chkAutoRefresh">每分钟自动刷新</label>
        </div>
        <div class="w-100"></div>
        <div id="statusChartDOM" class="echarts loading col mt-2"></div>
    </div>
@endsection

@section('script-after-container')
    <script>
        'use strict';
        new Vue({ el: '#navbar' , data: { $$baseUrl, activeNav: 'status' } });

        let statusChartVue = new Vue({
            el: '#statusChart',
            data: {
                autoRefresh: false
            },
            watch: {
                autoRefresh: function (autoRefresh) {
                    if (autoRefresh) {
                        this.autoRefreshIntervalID = setInterval(() => {
                            grecaptcha.execute($$reCAPTCHASiteKey, { action: window.location.pathname }).then((token) => {
                                $reCAPTCHAToken = token;
                                loadStatusData();
                            });
                        }, 60000); // refresh data every minute
                    } else {
                        clearInterval(this.autoRefreshIntervalID);
                    }
                }
            }
        });

        let statusChartDOM = $('#statusChartDOM');
        let statusChart = echarts.init(statusChartDOM[0]);

        let loadStatusData = () => {
            $.getJSON(`${$$baseUrl}/api/status`, { 'reCAPTCHA': $reCAPTCHAToken }).done(function (jsonData) {
                let selectColumnFromStatus = (prop) => {
                    return _.map(jsonData, (item) => {
                        return _.values(_.pick(item, ['startTime', prop]));
                    });
                };
                statusChart.setOption({
                    series: [
                        {
                            name: '耗时',
                            data: selectColumnFromStatus('duration')
                        },
                        {
                            name: '网络请求量',
                            data: selectColumnFromStatus('webRequestTimes')
                        },
                        {
                            name: '处理贴子量',
                            data: selectColumnFromStatus('parsedPostTimes')
                        },
                        {
                            name: '处理用户量',
                            data: selectColumnFromStatus('parsedUserTimes')
                        }
                    ]
                });
            });
        };

        let $reCAPTCHAToken;
        grecaptcha.ready(() => {
            grecaptcha.execute($$reCAPTCHASiteKey, { action: window.location.pathname }).then((token) => {
                $reCAPTCHAToken = token;
                statusChart.setOption({
                    title: {
                        text: '近期性能统计'
                    },
                    tooltip: {
                        trigger: 'axis'
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
                    legend: {
                        data:[
                            'type',
                            'fid',
                            'tid',
                            'pid',
                            '耗时',
                            '网络请求量',
                            '处理贴子量',
                            '处理用户量'
                        ]
                    },
                    dataZoom: [
                        {
                            type: 'slider',
                            xAxisIndex: [0, 1],
                            filterMode: 'filter',
                            start: 90
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
                    grid: [
                        {
                            height: '35%'
                        },
                        {
                            height: '35%',
                            top: '60%'
                        }
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
                            name: '耗时',
                            type: 'line',
                            step: 'middle',
                            symbolSize : 2,
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
                            name: '网络请求量',
                            xAxisIndex: 1,
                            yAxisIndex: 2,
                            type: 'line',
                            symbolSize : 2,
                            sampling: 'average',
                        },
                        {
                            name: '处理贴子量',
                            xAxisIndex: 1,
                            yAxisIndex: 1,
                            type: 'line',
                            symbolSize : 2,
                            sampling: 'average',
                            areaStyle: {},
                        },
                        {
                            name: '处理用户量',
                            xAxisIndex: 1,
                            yAxisIndex: 1,
                            type: 'line',
                            symbolSize : 2,
                            sampling: 'average',
                            areaStyle: {},
                        }
                    ]
                });
                loadStatusData();
                statusChartDOM.removeClass('loading');
            });
        });
    </script>
@endsection