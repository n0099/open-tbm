@extends('layout')
@php($baseUrl = env('APP_URL'))

@section('title', '状态')

@section('container')
    <style>
        #statusChartDOM {
            height: 32em;
        }
    </style>
    <div id="statusChartDOM" class="mt-2"></div>
@endsection

@section('script-after-container')
    <script src="https://cdn.jsdelivr.net/npm/echarts@4.1.0/dist/echarts.min.js"></script>
    <script>
        'use strict';
        new Vue({ el: '#navbar' , data: { $$baseUrl, activeNav: 'status' } });

        let statusChart = echarts.init($('#statusChartDOM')[0]);
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
                },
                {
                    show: true,
                    type: 'continuous',
                    seriesIndex: 1,
                    min: 0,
                    max: 240
                },
                {
                    show: true,
                    type: 'continuous',
                    seriesIndex: 2,
                    min: 0,
                    max: 5000
                },
                {
                    show: true,
                    type: 'continuous',
                    seriesIndex: 3,
                    min: 0,
                    max: 1500
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
                    step: 'end',
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
                    hoverAnimation: false
                },
                {
                    name: '处理贴子量',
                    xAxisIndex: 1,
                    yAxisIndex: 1,
                    type: 'line',
                    symbolSize : 2,
                    sampling: 'average',
                    areaStyle: {},
                    hoverAnimation: false
                },
                {
                    name: '处理用户量',
                    xAxisIndex: 1,
                    yAxisIndex: 1,
                    type: 'line',
                    symbolSize : 2,
                    sampling: 'average',
                    areaStyle: {},
                    hoverAnimation: false
                }
            ]
        });
        $.getJSON(`${$$baseUrl}/api/status`).done(function (jsonData) {
            jsonData = _.sortBy(_.map(jsonData, (item) => {
                return _.mapValues(item, (item, index) => {
                    return index === 'startTime' ? moment.unix(item).format(moment.HTML5_FMT.DATETIME_LOCAL) : item;
                })
            }), 'startTime');
            let groupStatusByStartMinute = (prop) => {
                return _.chain(jsonData)
                    .map((item) => {
                        return _.pick(item, ['startTime', prop]);
                    })
                    .groupBy((item) => {
                        return item.startTime;
                    })
                    .mapValues((item) => {
                        return _.sumBy(item, prop);
                    })
                    .toPairs()
                    .value();
            };
            statusChart.setOption({
                series: [
                    {
                        name: '耗时',
                        data: groupStatusByStartMinute('duration')
                    },
                    {
                        name: '网络请求量',
                        data: groupStatusByStartMinute('webRequestTimes')
                    },
                    {
                        name: '处理贴子量',
                        data: groupStatusByStartMinute('parsedPostTimes')
                    },
                    {
                        name: '处理用户量',
                        data: groupStatusByStartMinute('parsedUserTimes')
                    }
                ]
            })
        });
    </script>
@endsection