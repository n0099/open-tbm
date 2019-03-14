@extends('layout')

@section('title', 'bilibili吧吧主公投 - 专题')

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
        new Vue({ el: '#navbar' , data: { $$baseUrl, activeNav: 'bilibiliVote' } });

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
                            reCAPTCHACheck.then((token) => {
                                loadTop10CandidatesCounts(token);
                            });
                        }, 60000); // refresh data every minute
                    } else {
                        clearInterval(this.autoRefreshIntervalID);
                    }
                },
                'statsQuery.top10CandidatesTimeRange': function (top10CandidatesTimeRange) {
                    reCAPTCHACheck.then((token) => {
                        loadTop10CandidatesTimelineStats(token, top10CandidatesTimeRange);
                    });
                }
            },
            created: function () {
                reCAPTCHACheck.then((token) => {
                    countStatsChart.setOption({
                        title: {
                            text: 'bilibili吧吧主公投 前10票数',
                            subtext: '候选人间线上数字为与前一人票差\n数据仅供参考 数据来源：四叶贴吧云监控 四叶QQ群：292311751'
                        },
                        tooltip: {
                            trigger: 'axis',
                            axisPointer: {type: 'shadow'}
                        },
                        toolbox: {
                            feature: {
                                dataZoom: {show: true, yAxisIndex: 'none'},
                                restore: {show: true},
                                dataView: {show: true},
                                saveAsImage: {show: true},
                                magicType: {show: true, type: ['stack', 'tiled']},
                            }
                        },
                        legend: {
                            data: ['有效票', '无效票', '有效投票者平均等级', '无效投票者平均等级']
                        },
                        xAxis: {
                            type: 'category'
                        },
                        yAxis: [
                            {
                                type: 'value',
                            },
                            {
                                type: 'value',
                                splitLine: { show: false }
                            }
                        ],
                        series: [
                            {
                                id: 'validVotesCount',
                                name: '有效票',
                                type: 'bar',
                                label: {
                                    show: true,
                                    position: 'top',
                                    color: '#fe980e'
                                },
                                //stack: 'votesCount',
                                z: 1 // prevent label covered by invalidVotesCount categroy
                            },
                            {
                                id: 'invalidVotesCount',
                                name: '无效票',
                                type: 'bar',
                                //stack: 'votesCount',
                                z: 0
                            },
                            {
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
                            }
                        ]
                    });
                    loadTop10CandidatesCounts(token);
                });
                reCAPTCHACheck.then((token) => {
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
                            /!*{
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
                            }*!/
                        ]
                    });*/
                    timeLineStatsChart.setOption({
                        baseOption: {
                            timeline: {
                                playInterval: 300,
                                symbol: 'none',
                                realtime: true,
                                left: 0,
                                right: 0,
                                bottom: 0,
                                label: { show: false }
                            },
                            title: {
                                text: 'bilibili吧吧主公投 前10票数 时间轴',
                                subtext: '候选人间线上数字为与前一人票差\n数据仅供参考 数据来源：四叶贴吧云监控 四叶QQ群：292311751'
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
                                    id: 'validVotesCount',
                                    name: '有效票',
                                    type: 'bar',
                                    label: {
                                        show: true,
                                        position: 'right',
                                        color: '#fe980e'
                                    },
                                    z: 1 // prevent label covered by invalidVotesCount categroy
                                },
                                {
                                    id: 'invalidVotesCount',
                                    name: '无效票',
                                    type: 'bar',
                                    label: {
                                        show: true,
                                        position: 'right',
                                        color: '#999999'
                                    },
                                    z: 0,
                                    barGap: '0%'
                                }
                            ]
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
                let top10Candidates = _.map(_.map(_.filter(jsonData, { isValid: 1 }), 'voteFor'), (i) => i + '号');
                let validVotes = _.filter(jsonData, { isValid: 1 });
                let invalidVotes = _.filter(jsonData, { isValid: 0 });
                let validVotesCount = _.map(validVotes, 'count');
                let invalidVotesCount = _.map(invalidVotes, 'count');
                let validVotesCountDiffWithPrevious = _.map(validVotesCount, (count, index) => {
                    return [
                        {
                            label: {
                                show: true,
                                position: 'middle',
                                formatter: (-(count - validVotesCount[index - 1])).toString()
                            },
                            coord: [index, count]
                        },
                        {
                            coord: [index - 1, validVotesCount[index - 1]]
                        }
                    ];
                });
                validVotesCountDiffWithPrevious.shift(); // first candidate doesn't needs to exceed anyone
                countStatsChart.setOption({
                    xAxis: {
                        data: top10Candidates
                    },
                    series: [
                        {
                            id: 'validVotesCount',
                            data: validVotesCount,
                            markLine: {
                                lineStyle: {
                                    normal: { type: 'dashed' }
                                },
                                symbol: 'none',
                                data: validVotesCountDiffWithPrevious
                            }
                        },
                        {
                            id: 'invalidVotesCount',
                            data: invalidVotesCount
                        }
                    ]
                });
                countStatsChartDOM.removeClass('loading');
            });
        };

        let timeLineStatsChartDOM = $('#timeLineStatsChartDOM');
        let timeLineStatsChart = echarts.init(timeLineStatsChartDOM[0]);
        let loadTop10CandidatesTimelineStats = (reCAPTCHAToken, timeRange) => {
            $.getJSON(`${$$baseUrl}/api/bilibiliVote/top10CandidatesStats`, $.param(_.merge({ type: 'timeline', timeRange }, reCAPTCHAToken))).then((jsonData) => {
                window.jsonData = jsonData;
                let options = [];
                /*let voteStartTime = moment('2019-03-10T12:35:00').unix();
                let voteEndTime = moment('2019-03-11T12:00:00').unix();
                let voteTimeGroupRange = 5 * 60; // 5 mins
                let timeGroups = _.range(voteStartTime, voteEndTime + voteTimeGroupRange, voteTimeGroupRange);*/
                _.each(_.groupBy(jsonData, 'endTime'), (timeGroup, time) => {
                    let validVotes = _.filter(timeGroup, { isValid: 1 });
                    let invalidVotes = _.filter(timeGroup, { isValid: 0 });
                    let invalidVotesCount = _.map(invalidVotes, 'count');
                    let validVotesCount = _.map(validVotes, 'count');
                    let validVotesCountDiffWithPrevious = _.map(validVotesCount, (count, index) => {
                        return [
                            {
                                label: {
                                    show: true,
                                    position: 'middle',
                                    formatter: (-(count - validVotesCount[index + 1])).toString()
                                },
                                coord: [count, index]
                            },
                            {
                                coord: [validVotesCount[index + 1], index + 1]
                            }
                        ];
                    });
                    validVotesCountDiffWithPrevious.pop(); // first candidate doesn't needs to exceed anyone
                    options.push({
                        yAxis: {
                            data: _.map(validVotes, 'voteFor')
                        },
                        series: [
                            {
                                id: 'validVotesCount',
                                data: validVotesCount,
                                markLine: {
                                    lineStyle: {
                                        normal: { type: 'dashed' }
                                    },
                                    symbol: 'none',
                                    data: validVotesCountDiffWithPrevious
                                }
                            },
                            {
                                id: 'invalidVotesCount',
                                data: invalidVotesCount
                            }
                        ],
                        graphic: [
                            {
                                type: 'text',
                                right: '10%',
                                bottom: '10%',
                                z: 100,
                                style: {
                                    fill: '#989898',
                                    text: moment.unix(time).format('M-D H:mm'),
                                    font: 'bold 26px Microsoft YaHei'
                                }
                            }
                        ]
                    });
                });

                timeLineStatsChart.setOption({
                    baseOption: {
                        timeline: {
                            autoPlay: true,
                            data: _.map(_.sortBy(_.uniq(_.map(jsonData, 'endTime'))), (i) => moment.unix(i).format())
                        }
                    },
                    options
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