@extends('layout')

@section('title', 'bilibili吧2019年吧主公投 - 专题')

@section('container')
    <style>
        #countStatsChartDOM {
            height: 20em;
        }
        #timeLineStatsChartDOM {
            height: 40em;
        }
    </style>
    <div id="bilibiliVote" class="mt-2">
        <div class="justify-content-end row">
            <div class="col-3 custom-checkbox custom-control">
                <input v-model="autoRefresh" id="chkAutoRefresh" type="checkbox" class="custom-control-input">
                <label class="custom-control-label" for="chkAutoRefresh"><del>每分钟自动刷新</del></label>
            </div>
        </div>
        <div id="countStatsChartDOM" class="echarts loading row mt-2"></div>
        <div class="justify-content-end form-group form-row">
            <label class="col-2 col-form-label" for="queryStatsTop10CandidatesTimeRange">时间粒度</label>
            <div class="col-2 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                </div>
                <select v-model="statsQuery.top10CandidatesTimeRange" id="queryStatsTop10CandidatesTimeRange" class="form-control">
                    <option value="minute">分钟</option>
                    <option value="hour">小时</option>
                </select>
            </div>
        </div>
        <div id="timeLineStatsChartDOM" class="echarts loading row mt-2"></div>
    </div>
@endsection

@section('script-after-container')
    <script>
        'use strict';
        $$initialNavBar('bilibiliVote');

        let bilibiliVoteVue = new Vue({
            el: '#bilibiliVote',
            data: {
                autoRefresh: false,
                statsQuery: {
                    top10CandidatesTimeRange: 'minute'
                }
            },
            watch: {
                autoRefresh: function (autoRefresh) {
                    if (autoRefresh) {
                        this.autoRefreshIntervalID = setInterval(() => {
                            $$reCAPTCHACheck().then((token) => {
                                loadTop10CandidatesCounts(token);
                            });
                        }, 60000); // refresh data every minute
                    } else {
                        clearInterval(this.autoRefreshIntervalID);
                    }
                },
                'statsQuery.top10CandidatesTimeRange': function (top10CandidatesTimeRange) {
                    $$reCAPTCHACheck().then((token) => {
                        loadTop10CandidatesTimelineStats(token, top10CandidatesTimeRange);
                    });
                }
            },
            methods: {
                formatCandidateNameById: function (id) {
                    return `${id}号\n${this.$data.candidatesName[id - 1]}`;
                }
            },
            created: function () {
                $.getJSON(`${$$baseUrl}/api/bilibiliVote/candidatesName.json`).then((jsonData) => {
                    this.$data.candidatesName = jsonData;
                });
                $$reCAPTCHACheck().then((token) => {
                    countStatsChart.setOption({
                        title: {
                            text: 'bilibili吧吧主公投 前10票数',
                            subtext: '候选人间线上数字为与前一人票差 数据仅供参考 来源：四叶贴吧云监控 QQ群：292311751'
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
                        legend: {
                            data: ['有效票', '无效票', '有效投票者平均等级', '无效投票者平均等级']
                        },
                        xAxis: {
                            type: 'category',
                            axisLabel: { interval: 0, rotate: 30 }
                        },
                        yAxis: [
                            {
                                type: 'value'
                            },
                            {
                                type: 'value',
                                splitLine: { show: false }
                            }
                        ],
                        series: [
                            {
                                id: 'validCount',
                                name: '有效票',
                                type: 'bar',
                                label: {
                                    show: true,
                                    position: 'top',
                                    color: '#fe980e'
                                },
                                z: 1 // prevent label covered by invalidCount categroy
                            },
                            {
                                id: 'invalidCount',
                                name: '无效票',
                                type: 'bar',
                                z: 0
                            },
                            /*{
                                id: 'validVotesVoterAvgGrade',
                                name: '有效投票者平均等级',
                                type: 'line',
                                yAxisIndex: 1
                            },
                            {
                                id: 'invalidVotesVoterAvgGrade',
                                name: '无效投票者平均等级',
                                type: 'line',
                                yAxisIndex: 1,
                                barGap: '0%'
                            }*/
                        ]
                    });
                    loadTop10CandidatesCounts(token);
                });
                /*timeLineStatsChart.setOption({
                    title: {
                        text: 'bilibili吧吧主公投 前10票数历史增量',
                        subtext: '数据来源：四叶贴吧云监控 四叶QQ群：292311751'
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
                    legend: {
                        //data: ['有效票', '无效票', '有效票增量', '无效票增量']
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
                    grid: [
                        { bottom: '0%' }, // ???
                    ],
                    xAxis: {
                        type: 'time'
                    },
                    yAxis: {
                        type: 'value',
                    },
                    series: [
                        {
                            id: 'thread',
                            name: '有效票',
                            type: 'line',
                            symbolSize: 2,
                            stack: 'postsCount'
                        },
                        {
                            id: 'reply',
                            name: '回复贴',
                            type: 'line',
                            stack: 'postsCount'
                        },
                        {
                            id: 'subReply',
                            name: '楼中楼',
                            type: 'line',
                            symbolSize: 2,
                            label: {
                                show: true,
                                position: 'top'
                            },
                            stack: 'postsCount'
                        }
                    ]
                });*/
                $$reCAPTCHACheck().then((token) => {
                    let votesCountSeriesLabelFormatter = (votesData, currentCount, candidateName) => {
                        let timeline = timeLineStatsChart.getOption().timeline[0];
                        let previousTimelineValue = _.find(votesData, {
                            endTime: moment(timeline.data[timeline.currentIndex - 1]).unix().toString(),
                            voteFor: _.truncate(candidateName, { length: candidateName.indexOf('号'), omission: '' }) // trim series name from '1号' to '1'
                        });
                        previousTimelineValue = previousTimelineValue === undefined ? 0 : previousTimelineValue.count;
                        return `${currentCount} (+${currentCount - previousTimelineValue})`;
                    };
                    timeLineStatsChart.setOption({
                        baseOption: {
                            timeline: {
                                playInterval: 300,
                                symbol: 'none',
                                realtime: true,
                                loop: false,
                                left: 0,
                                right: 0,
                                bottom: 0,
                                label: { show: false }
                            },
                            title: {
                                text: 'bilibili吧吧主公投 前10票数 时间轴',
                                subtext: '候选人间线上数字为与前一人票差\n数据仅供参考 来源：四叶贴吧云监控 QQ群：292311751'
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
                            legend: {
                                data: ['有效票', '无效票', '贴吧官方统计有效票']
                            },
                            xAxis: [
                                {
                                    type: 'value',
                                    splitLine: { show: false },
                                    splitArea: { show: true }
                                }
                            ],
                            yAxis: {
                                type: 'category'
                            },
                            series: [
                                {
                                    id: 'officalValidCount',
                                    name: '贴吧官方统计有效票',
                                    type: 'bar',
                                    label: {
                                        show: true,
                                        position: 'right',
                                        formatter: (params) => {
                                            return `${params.value.officalValidCount} 相差${params.value.officalValidCount - params.value.validCount}`;
                                        }
                                    },
                                    encode: {
                                        x: 'officalValidCount',
                                        y: 'voteFor'
                                    },
                                    itemStyle: { color: '#91c7ae' }
                                },
                                {
                                    id: 'validCount',
                                    name: '有效票',
                                    type: 'bar',
                                    label: {
                                        show: true,
                                        position: 'right',
                                        color: '#fe980e',
                                        formatter: (params) => votesCountSeriesLabelFormatter(window.timelineValidVotes, params.value.validCount, params.name)
                                    },
                                    z: 1, // prevent label covered by invalidCount categroy
                                    encode: {
                                        x: 'validCount',
                                        y: 'voteFor'
                                    },
                                    itemStyle: { color: '#c23531' }
                                },
                                {
                                    id: 'invalidCount',
                                    name: '无效票',
                                    type: 'bar',
                                    label: {
                                        show: true,
                                        position: 'right',
                                        color: '#999999',
                                        formatter: (params) => votesCountSeriesLabelFormatter(window.timelineInvalidVotes, params.value.invalidCount, params.name)
                                    },
                                    z: 0,
                                    barGap: '0%',
                                    encode: {
                                        x: 'invalidCount',
                                        y: 'voteFor'
                                    },
                                    itemStyle: { color: '#2f4554' }
                                },
                                {
                                    id: 'totalVotesValidation',
                                    type: 'pie',
                                    center: ['85%', '35%'],
                                    radius: ['25%', '8%'],
                                    label: {
                                        show: true,
                                        position: 'inside',
                                        formatter: '{b}\n{c}\n{d}%'
                                    }
                                }
                            ],
                            graphic: {
                                type: 'text',
                                right: '15%',
                                bottom: '15%',
                                z: 100,
                                style: {
                                    fill: '#989898',
                                    font: '28px Microsoft YaHei',
                                    textAlign: 'right'
                                }
                            }
                        }
                    });
                    loadTop10CandidatesTimelineStats(token, this.$data.statsQuery.top10CandidatesTimeRange);
                });
            }
        });

        let countStatsChartDOM = $('#countStatsChartDOM');
        let countStatsChart = echarts.init(countStatsChartDOM[0]);
        let loadTop10CandidatesCounts = (reCAPTCHAToken) => {
            $.getJSON(`${$$baseUrl}/api/bilibiliVote/top10CandidatesStats`, $.param(_.merge({ type: 'count' }, reCAPTCHAToken))).then((jsonData) => {
                /* dataset should be like
                    [
                        { voteFor: 1, validCount: 1, invalidCount: 0 },
                        ...
                    ]
                */
                let dataset = _.chain(jsonData)
                    .groupBy('voteFor')
                    .sortBy((group) => jsonData.indexOf(group[0]))
                    .map((candidateVotes) => {
                        let validCount = _.find(candidateVotes, { isValid: 1 });
                        validCount = validCount == null ? 0 : validCount.count;
                        let invalidCount = _.find(candidateVotes, { isValid: 0 });
                        invalidCount = invalidCount == null ? 0 : invalidCount.count;
                        return {
                            voteFor: bilibiliVoteVue.formatCandidateNameById(candidateVotes[0].voteFor),
                            validCount,
                            invalidCount
                        }
                    })
                    .value();
                let valiCounts = _.map(dataset, 'validCount');
                let validCountsDiffWithPrevious = _.map(valiCounts, (count, index) => {
                    return [
                        {
                            label: {
                                show: true,
                                position: 'middle',
                                formatter: (-(count - valiCounts[index - 1])).toString()
                            },
                            coord: [index, count]
                        },
                        {
                            coord: [index - 1, valiCounts[index - 1]]
                        }
                    ];
                });
                validCountsDiffWithPrevious.shift(); // first candidate doesn't needs to exceed anyone
                countStatsChart.setOption({
                    dataset: { source: dataset },
                    series: [
                        {
                            id: 'validCount',
                            markLine: {
                                lineStyle: {
                                    normal: { type: 'dashed' }
                                },
                                symbol: 'none',
                                data: validCountsDiffWithPrevious
                            },
                        }
                    ],
                });
                countStatsChartDOM.removeClass('loading');
            });
        };

        let timeLineStatsChartDOM = $('#timeLineStatsChartDOM');
        let timeLineStatsChart = echarts.init(timeLineStatsChartDOM[0]);
        let loadTop10CandidatesTimelineStats = (reCAPTCHAToken, timeRange) => {
            $.getJSON(`${$$baseUrl}/api/bilibiliVote/top10CandidatesStats`, $.param(_.merge({ type: 'timeline', timeRange }, reCAPTCHAToken))).then((jsonData) => {
                window.timelineValidVotes = _.filter(jsonData, { isValid: 1 });
                window.timelineInvalidVotes = _.filter(jsonData, { isValid: 0 });
                let options = [];
                _.each(_.groupBy(jsonData, 'endTime'), (timeGroup, time) => {
                    /* dataset should be like
                    [
                        { voteFor: 1, validCount: 1, invalidCount: 0 },
                        ...
                    ]
                    */
                    let dataset = _.chain(timeGroup)
                        .sortBy('count')
                        .groupBy('voteFor')
                        .sortBy((group) => _.chain(group)
                            .map('count')
                            .map((i) => parseInt(i))
                            .sum()
                            .value()
                        )
                        .map((candidateVotes) => {
                            let validCount = _.find(candidateVotes, { isValid: 1 });
                            validCount = validCount == null ? 0 : validCount.count;
                            let invalidCount = _.find(candidateVotes, { isValid: 0 });
                            invalidCount = invalidCount == null ? 0 : invalidCount.count;
                            return {
                                voteFor: bilibiliVoteVue.formatCandidateNameById(candidateVotes[0].voteFor),
                                validCount,
                                invalidCount,
                                officalValidCount: null
                            }
                        })
                        .value();
                    let validCounts = _.map(dataset, 'validCount');
                    let validCountsDiffWithPrevious = _.map(validCounts, function (count, index) {
                        return [
                            {
                                label: {
                                    show: true,
                                    position: 'middle',
                                    formatter: (-(count - validCounts[index + 1])).toString()
                                },
                                coord: [count, index]
                            },
                            {
                                coord: [validCounts[index + 1], index + 1]
                            }
                        ];
                    });
                    validCountsDiffWithPrevious = validCountsDiffWithPrevious.slice(-5, -1);
                    let totalVotesCount = (isValid = null) => {
                        let votesSumCount = _.chain(timeGroup);
                        if (isValid != null) {
                            votesSumCount = votesSumCount.filter({ isValid })
                        }
                        return votesSumCount
                            .map('count')
                            .map((i) => parseInt(i))
                            .sum()
                            .value()
                    };
                    let totalValidVotes = totalVotesCount(1);
                    let totalInvalidVotes = totalVotesCount(0);
                    options.push({
                        dataset: { source: dataset },
                        series: [
                            {
                                id: 'validCount',
                                markLine: {
                                    lineStyle: {
                                        normal: { type: 'dashed' }
                                    },
                                    symbol: 'none',
                                    data: validCountsDiffWithPrevious
                                }
                            },
                            {
                                id: 'totalVotesValidation',
                                data: [
                                    {
                                        name: '有效票',
                                        value: totalValidVotes
                                    },
                                    {
                                        name: '无效票',
                                        value: totalInvalidVotes
                                    }
                                ]
                            }
                        ],
                        graphic: {
                            style: {
                                text: `共${totalVotesCount()}票\n${moment.unix(time).format('M-D H:mm')}`,
                            }
                        }
                    });
                });
                let originTimelineOptions = _.cloneDeep(options[options.length - 1]);
                _.remove(originTimelineOptions.series, { id: 'totalVotesValidation' });
                options.push(_.merge(originTimelineOptions, {
                    dataset: {
                        source: _.map(_.sortBy([
                            { voteFor: 50, officalValidCount: 4562 },
                            { voteFor: 779, officalValidCount: 3747 },
                            { voteFor: 1011, officalValidCount: 2261 },
                            { voteFor: 840, officalValidCount: 655 },
                            { voteFor: 48, officalValidCount: 129 },
                            { voteFor: 1, officalValidCount: 80 },
                            { voteFor: 58, officalValidCount: 74 },
                            { voteFor: 2, officalValidCount: 56 },
                            { voteFor: 452, officalValidCount: 55 },
                            { voteFor: 40, officalValidCount: 45 }
                        ], 'officalValidCount'), (officalCounts) => {
                            return { voteFor: bilibiliVoteVue.formatCandidateNameById(officalCounts.voteFor), officalValidCount: officalCounts.officalValidCount };
                        })
                    },
                    series: originTimelineOptions.series.concat({
                        id: 'totalVotesValidation',
                        data: [
                            {
                                name: '官方有效票',
                                value: 12247
                            },
                            {
                                name: '官方无效票',
                                value: 473
                            }
                        ]
                    }),
                    graphic: {
                        style: {
                            text: '贴吧官方统计共12720票\n有效12247票 无效473票\n3-11 18:26',
                        }
                    }
                }));
                let timelineRanges = _.map(_.sortBy(_.uniq(_.map(jsonData, 'endTime'))), (i) => moment.unix(i).format());
                timelineRanges.push('2019-03-11T18:26:40+08:00');
                timeLineStatsChart.setOption({
                    baseOption: {
                        timeline: {
                            autoPlay: true,
                            data: timelineRanges
                        }
                    },
                    options
                });
                timeLineStatsChart.on('timelinechanged', (params) => {
                    if (params.currentIndex + 1 === timeLineStatsChart.getOption().timeline[0].data.length) {
                        timeLineStatsChart.dispatchAction({
                            type: 'legendSelect',
                            name: '贴吧官方统计有效票'
                        });
                    } else {
                        timeLineStatsChart.dispatchAction({
                            type: 'legendUnSelect',
                            name: '贴吧官方统计有效票'
                        });
                    }
                });
                /*let top10Candidates = _.uniq(_.map(_.filter(jsonData, { isValid: 1 }), 'voteFor')); // not order by votes count
                let validVotes = _.filter(jsonData, { isValid: 1 });
                let invalidVotes = _.filter(jsonData, { isValid: 0 });

                let series = [];
                _.each(top10Candidates, (candidateId) => {
                    series.push({
                        name: `${candidateId}号有效票`,
                        type: 'line',
                        data: _.map(_.filter(validVotes, { voteFor: candidateId }), (i) => [i.time, i.count])
                    });
                });
                timeLineStatsChart.setOption({
                    series
                });*/
                timeLineStatsChartDOM.removeClass('loading');
            });
        };
    </script>
@endsection