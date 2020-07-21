@extends('layout')
@include('module.echarts')
@include('module.vue.antd')

@section('title', 'bilibili吧2019年吧主公投 - 专题')

@section('style')
    <style>
        #top50CandidatesCountChartDOM {
            height: 32em;
        }
        #candidatesTimelineChartDOM {
            height: 40em;
        }
        #top5CandidatesCountByTimeChartDOM {
            height: 40em;
        }
        #countByTimeChartDOM {
            height: 20em;
        }
    </style>
@endsection

@section('container')
    <div id="bilibiliVote" class="mt-2">
        <p>有效票定义:</p>
        <ul>
            <li>投票人吧内等级大于4</li>
            <li><del>投票者回复内本人ID（xxx投yyy中xxx）与百度ID（非昵称）一致</del></li>
            <li>被投候选人序号有效（1~1056）</li>
            <li>此前未有过有效投票（即改票）</li>
        </ul>
        <p><a href="https://github.com/n0099/bilibiliVote" target="_blank">原始数据@GitHub</a></p>
        <p><a href="https://tieba.baidu.com/p/6059516291" target="_blank">关于启动本吧吧主招募的公示</a></p>
        <p><a href="https://tieba.baidu.com/p/6062186860" target="_blank">【吧主招募】bilibili吧吧主候选人吧友投票贴</a></p>
        <p><a href="https://tieba.baidu.com/p/6063655698" target="_blank">Bilibili吧吧主招募投票结果公示</a></p>
        <p><a href="https://tieba.baidu.com/p/6061937239" target="_blank">吧务候选名单详细数据，含精品数（截止3月10日00时15分）</a></p>
        <p><a href="https://tieba.baidu.com/p/6062515014" target="_blank">B吧吧主候选人 支持率Top10 含支持者等级分布</a></p>
        <p><a href="https://tieba.baidu.com/p/6062736510" target="_blank">【数据分享】炎魔 五娃 奶茶的支持者都关注哪些贴吧？</a></p>
        <p><a href="https://tieba.baidu.com/p/6063625612" target="_blank">bilibili吧 吧主候选人支持率Top20（非官方数据，仅供参考）</a></p>
        <p><a href="https://www.bilibili.com/video/av46507371" target="_blank">【数据可视化】一分钟看完bilibili吧吧主公投</a></p>
        <hr />
        <div id="top50CandidatesCountChartDOM" class="echarts loading row mt-2"></div>
        <hr />
        <div id="candidatesTimelineChartDOM" class="echarts loading row mt-2"></div>
        <hr />
        <div class="justify-content-end form-group form-row">
            <label class="col-2 col-form-label text-right" for="queryCandidateCountByTimeTimeRange">时间粒度</label>
            <div class="col-2 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                </div>
                <select v-model="statsQuery.candidateCountByTimeTimeRange" id="queryCandidateCountByTimeTimeRange" class="custom-select form-control">
                    <option value="minute">分钟</option>
                    <option value="hour">小时</option>
                </select>
            </div>
        </div>
        <div id="top5CandidatesCountByTimeChartDOM" class="echarts loading row mt-2"></div>
        <hr />
        <div class="justify-content-end form-group form-row">
            <label class="col-2 col-form-label text-right" for="queryCountByTimeTimeRange">时间粒度</label>
            <div class="col-2 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="far fa-clock"></i></span>
                </div>
                <select v-model="statsQuery.countByTimeTimeRange" id="queryCountByTimeTimeRange" class="custom-select form-control">
                    <option value="minute">分钟</option>
                    <option value="hour">小时</option>
                </select>
            </div>
        </div>
        <div id="countByTimeChartDOM" class="echarts loading row mt-2"></div>
        <hr />
        <a-table :columns="candidatesDetailColumns" :data-source="candidatesDetailData" :pagination="{ pageSize: 50, pageSizeOptions: ['20', '50', '100', '200', '1056'], showSizeChanger: true }">
            <a slot="candidateName" slot-scope="text" :href="$data.$$getTiebaUserLink(text)">@{{ text }}</a>
        </a-table>
    </div>
@endsection

@section('script')
    <script>
        'use strict';
        $$initialNavBar('bilibiliVote');

        let top50CandidatesCountChartDOM;
        let top50CandidatesCountChart;
        const initialTop50CandidatesCountChart = () => {
            top50CandidatesCountChartDOM = $('#top50CandidatesCountChartDOM');
            top50CandidatesCountChart = echarts.init(top50CandidatesCountChartDOM[0]);
            top50CandidatesCountChart.setOption({
                title: {
                    text: 'bilibili吧吧主公投 前50候选人票数',
                    subtext: '候选人间线上数字为与前一人票差 数据仅供参考 来源：四叶贴吧云监控 QQ群：292311751'
                },
                axisPointer: {
                    link: { xAxisIndex: 'all' }
                },
                tooltip: {
                    trigger: 'axis',
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
                legend: { left: '30%' },
                dataZoom: [
                    {
                        type: 'slider',
                        filterMode: 'filter',
                        xAxisIndex: [0, 1],
                        startValue: 0,
                        endValue: 9,
                        bottom: 0
                    },
                    {
                        type: 'inside',
                        xAxisIndex: [0, 1],
                        filterMode: 'filter'
                    }
                ],
                grid: [
                    { height: '35%' },
                    { height: '40%', top: '58%' }
                ],
                xAxis: [
                    {
                        type: 'category',
                        axisLabel: { interval: 0, rotate: 30 }
                    },
                    {
                        type: 'category',
                        axisLabel: { show: false },
                        gridIndex: 1,
                        position: 'top'
                    }
                ],
                yAxis: [
                    {
                        type: 'value',
                        splitLine: { show: false },
                        splitArea: { show: true }
                    },
                    {
                        type: 'value',
                        splitLine: { show: false },
                        splitArea: { show: true },
                        inverse: true,
                        gridIndex: 1
                    }
                ],
                series: [
                    {
                        id: 'officialValidCount',
                        name: '贴吧官方统计有效票',
                        type: 'bar',
                        encode: {
                            x: 'voteFor',
                            y: 'officialValidCount'
                        }
                    },
                    {
                        id: 'validCount',
                        name: '有效票',
                        type: 'bar',
                        label: {
                            show: true,
                            position: 'top',
                            color: '#fe980e'
                        },
                        encode: {
                            x: 'voteFor',
                            y: 'validCount'
                        }
                    },
                    {
                        id: 'invalidCount',
                        name: '无效票',
                        type: 'bar',
                        z: 0,
                        barGap: '0%',
                        encode: {
                            x: 'voteFor',
                            y: 'invalidCount'
                        }
                    },
                    {
                        id: 'validVotesVoterAvgGrade',
                        name: '有效投票者平均等级',
                        type: 'bar',
                        xAxisIndex: 1,
                        yAxisIndex: 1,
                        encode: {
                            x: 'voteFor',
                            y: 'validAvgGrade'
                        }
                    },
                    {
                        id: 'invalidVotesVoterAvgGrade',
                        name: '无效投票者平均等级',
                        type: 'bar',
                        xAxisIndex: 1,
                        yAxisIndex: 1,
                        barGap: '0%',
                        encode: {
                            x: 'voteFor',
                            y: 'invalidAvgGrade'
                        }
                    }
                ]
            });
        };
        const loadTop50CandidatesCountChart = () => {
            $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                $.getJSON(`${$$baseUrl}/api/bilibiliVote/top50CandidatesVotesCount`, $.param(reCAPTCHAToken))
                    .done((ajaxData) => {
                        /*
                            [
                                { voteFor: 1, validVotesCount: 1, validVotesAvgGrade: 18, invalidVotesCount: 1, invalidVotesAvgGrade: 18 },
                                ...
                            ]
                         */
                        let dataset = _.chain(ajaxData)
                            .groupBy('voteFor')
                            .sortBy((candidate) => -_.sumBy(candidate, 'count')) // sort grouped candidate by it's total votes count descend
                            .map((candidateVotes) => {
                                let validVotes = _.find(candidateVotes, { isValid: 1 });
                                let validCount = validVotes == null ? 0 : validVotes.count;
                                let validAvgGrade = validVotes == null ? 0 : validVotes.voterAvgGrade;
                                let invalidVotes = _.find(candidateVotes, { isValid: 0 });
                                let invalidCount = invalidVotes == null ? 0 : invalidVotes.count;
                                let invalidAvgGrade = invalidVotes == null ? 0 : invalidVotes.voterAvgGrade;
                                let officialValidCount = _.find(bilibiliVoteVue.$data.top50OfficialValidVotesCount, { voteFor: parseInt(candidateVotes[0].voteFor) })
                                officialValidCount = officialValidCount == null ? 0 : officialValidCount.officialValidCount;
                                return {
                                    voteFor: bilibiliVoteVue.formatCandidateNameByID(candidateVotes[0].voteFor),
                                    validCount,
                                    validAvgGrade,
                                    invalidCount,
                                    invalidAvgGrade,
                                    officialValidCount
                                }
                            })
                            .value();

                        let validCount = _.map(dataset, 'validCount');
                        let validCountDiffWithPrevious = _.map(validCount, (count, index) => [
                            {
                                label: {
                                    show: true,
                                    position: 'middle',
                                    formatter: (-(count - validCount[index - 1])).toString()
                                },
                                coord: [index, count]
                            },
                            {
                                coord: [index - 1, validCount[index - 1]]
                            }
                        ]);
                        validCountDiffWithPrevious.shift(); // first candidate doesn't needs to exceed anyone

                        top50CandidatesCountChart.setOption({
                            dataset: { source: dataset },
                            series: [
                                {
                                    id: 'validCount',
                                    markLine: {
                                        lineStyle: {
                                            normal: { type: 'dashed' }
                                        },
                                        symbol: 'none',
                                        data: validCountDiffWithPrevious
                                    },
                                }
                            ],
                        });
                    })
                    .always(() => top50CandidatesCountChartDOM.removeClass('loading'));
            });
        };

        let top5CandidatesCountByTimeChartDOM;
        let top5CandidatesCountByTimeChart;
        const initialTop5CandidatesCountByTimeChart = () => {
            top5CandidatesCountByTimeChartDOM = $('#top5CandidatesCountByTimeChartDOM');
            top5CandidatesCountByTimeChart = echarts.init(top5CandidatesCountByTimeChartDOM[0]);
            top5CandidatesCountByTimeChart.setOption({
                title: {
                    text: 'bilibili吧吧主公投 前5票数分时增量',
                    subtext: '数据仅供参考 来源：四叶贴吧云监控 QQ群：292311751'
                },
                axisPointer: {
                    link: { xAxisIndex: 'all' }
                },
                tooltip: {
                    trigger: 'axis',
                },
                legend: {
                    type: 'scroll',
                    left: '30%'
                },
                dataZoom: [
                    {
                        type: 'slider',
                        xAxisIndex: [0, 1],
                        filterMode: 'filter',
                        end: 100,
                        bottom: '46%'
                    },
                    {
                        type: 'inside',
                        xAxisIndex: [0, 1],
                        filterMode: 'filter'
                    }
                ],
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
                    },
                    {
                        type: 'value',
                        gridIndex: 1,
                    }
                ]
            });
        };
        const loadTop5CandidatesCountByTimeChart = (timeRange) => {
            $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                $.getJSON(`${$$baseUrl}/api/bilibiliVote/top5CandidatesVotesCountByTime`,
                    $.param(_.merge({ timeRange }, reCAPTCHAToken)))
                    .done((ajaxData) => {
                        let top10Candidates = _.chain(ajaxData).filter({ isValid: 1 }).map('voteFor').uniq().value(); // not order by votes count
                        let validVotes = _.filter(ajaxData, { isValid: 1 });
                        let invalidVotes = _.filter(ajaxData, { isValid: 0 });

                        let series = [];
                        _.each(top10Candidates, (candidateID) => {
                            series.push({
                                name: `${candidateID}号有效票增量`,
                                type: 'line',
                                symbolSize: 2,
                                smooth: true,
                                data: _.map(_.filter(validVotes, { voteFor: candidateID }), (i) => [i.time, i.count])
                            });
                            series.push({
                                name: `${candidateID}号无效票增量`,
                                type: 'line',
                                symbolSize: 2,
                                smooth: true,
                                xAxisIndex: 1,
                                yAxisIndex: 1,
                                data: _.map(_.filter(invalidVotes, { voteFor: candidateID }), (i) => [i.time, i.count])
                            });
                        });
                        top5CandidatesCountByTimeChart.setOption({
                            axisPointer: {
                                label: {
                                    formatter: $$echartsTimeRangeAxisPointerLabelFormatter[timeRange]
                                }
                            },
                            xAxis: [
                                {
                                    type: $$echartsTimeRangeAxisType[timeRange]
                                },
                                {
                                    type: $$echartsTimeRangeAxisType[timeRange]
                                }
                            ],
                            series
                        });
                    })
                    .always(() => top5CandidatesCountByTimeChartDOM.removeClass('loading'));
            });
        };

        let countByTimeChartDOM;
        let countByTimeChart;
        const initialCountByTimeChart = () => {
            countByTimeChartDOM = $('#countByTimeChartDOM');
            countByTimeChart = echarts.init(countByTimeChartDOM[0]);
            countByTimeChart.setOption({
                title: {
                    text: 'bilibili吧吧主公投 总票数分时增量',
                    subtext: '数据仅供参考 来源：四叶贴吧云监控 QQ群：292311751'
                },
                tooltip: {
                    trigger: 'axis',
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
                legend: { left: '30%' },
                dataZoom: [
                    {
                        type: 'slider',
                        xAxisIndex: 0,
                        filterMode: 'filter',
                        end: 100,
                        bottom: 0
                    },
                    {
                        type: 'inside',
                        xAxisIndex: 0,
                        filterMode: 'filter'
                    }
                ],
                xAxis: {
                    type: 'time'
                },
                yAxis: {
                    type: 'value'
                },
                series: [
                    {
                        id: 'validCount',
                        name: '有效票增量',
                        type: 'line',
                        symbolSize: 2,
                        smooth: true,
                        encode: {
                            x: 'time',
                            y: 'validCount'
                        }
                    },
                    {
                        id: 'invalidCount',
                        name: '无效票增量',
                        type: 'line',
                        symbolSize: 2,
                        smooth: true,
                        encode: {
                            x: 'time',
                            y: 'invalidCount'
                        }
                    }
                ]
            });
        };
        const loadCountByTimeChart = (timeRange) => {
            $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                $.getJSON(`${$$baseUrl}/api/bilibiliVote/allVotesCountByTime`,
                    $.param(_.merge({ timeRange }, reCAPTCHAToken)))
                    .done((ajaxData) => {
                        /*
                            [
                                { time: '2019-03-11 12:00', validCount: 1, invalidCount: 0 },
                                ...
                            ]
                         */
                        let dataset = _.chain(ajaxData)
                            .groupBy('time')
                            .map((count, time) => {
                                let validCount = _.find(count, { isValid: 1 });
                                validCount = validCount == null ? 0 : validCount.count;
                                let invalidCount = _.find(count, { isValid: 0 });
                                invalidCount = invalidCount == null ? 0 : invalidCount.count;
                                return {
                                    time,
                                    validCount,
                                    invalidCount
                                };
                            })
                            .value();
                        countByTimeChart.setOption({
                            xAxis: {
                                type: $$echartsTimeRangeAxisType[timeRange],
                                axisPointer: {
                                    label: {
                                        formatter: $$echartsTimeRangeAxisPointerLabelFormatter[timeRange]
                                    }
                                }
                            },
                            dataset: { source: dataset },
                        });
                    })
                    .always(() => countByTimeChartDOM.removeClass('loading'));
            });
        };

        let candidatesTimelineChartDOM;
        let candidatesTimelineChart;
        const initialCandidatesTimelineChart = () => {
            candidatesTimelineChartDOM = $('#candidatesTimelineChartDOM');
            candidatesTimelineChart = echarts.init(candidatesTimelineChartDOM[0]);
            const votesCountSeriesLabelFormatter = (votesData, currentCount, candidateName) => {
                let timeline = candidatesTimelineChart.getOption().timeline[0];
                let previousTimelineValue = _.find(votesData, {
                    endTime: moment(timeline.data[timeline.currentIndex - 1]).unix().toString(),
                    voteFor: candidateName.substr(0, candidateName.indexOf('号')) // trim ending '号' in series name
                });
                previousTimelineValue = previousTimelineValue === undefined ? 0 : previousTimelineValue.count;
                return `${currentCount} (+${currentCount - previousTimelineValue})`;
            };
            candidatesTimelineChart.setOption({
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
                        text: 'bilibili吧吧主公投 前10候选人票数时间轴',
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
                        data: ['贴吧官方统计有效票', '有效票', '无效票']
                    },
                    xAxis: {
                        type: 'value',
                        splitLine: { show: false },
                        splitArea: { show: true }
                    }
                    ,
                    yAxis: {
                        type: 'category'
                    },
                    series: [
                        {
                            id: 'officialValidCount',
                            name: '贴吧官方统计有效票',
                            type: 'bar',
                            label: {
                                show: true,
                                position: 'right',
                                formatter: (params) => `${params.value.officialValidCount} 相差${params.value.officialValidCount - params.value.validCount}`
                            },
                            encode: {
                                x: 'officialValidCount',
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
        };
        const loadCandidatesTimelineChart = () => {
            $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                $.getJSON(`${$$baseUrl}/api/bilibiliVote/top10CandidatesTimeline`, $.param(reCAPTCHAToken))
                    .done((ajaxData) => {
                        window.timelineValidVotes = _.filter(ajaxData, { isValid: 1 });
                        window.timelineInvalidVotes = _.filter(ajaxData, { isValid: 0 });
                        let options = [];
                        _.each(_.groupBy(ajaxData, 'endTime'), (timeGroup, time) => {
                            /*
                                [
                                    { voteFor: 1, validCount: 1, invalidCount: 0, officialValidCount: null },
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
                                        voteFor: bilibiliVoteVue.formatCandidateNameByID(candidateVotes[0].voteFor),
                                        validCount,
                                        invalidCount,
                                        officialValidCount: null
                                    }
                                })
                                .value();

                            let validCount = _.map(dataset, 'validCount');
                            let validCountDiffWithPrevious = _.map(validCount, (count, index) => [
                                {
                                    label: {
                                        show: true,
                                        position: 'middle',
                                        formatter: (-(count - validCount[index + 1])).toString()
                                    },
                                    coord: [count, index]
                                },
                                {
                                    coord: [validCount[index + 1], index + 1]
                                }
                            ]);
                            validCountDiffWithPrevious = validCountDiffWithPrevious.slice(-5, -1);

                            const totalVotesCount = (isValid = null) => {
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
                                            data: validCountDiffWithPrevious
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

                        // clone last timeline option then transform it to official votes count option
                        let originTimelineOptions = _.cloneDeep(options[options.length - 1]);
                        _.remove(originTimelineOptions.series, { id: 'totalVotesValidation' });
                        options.push(_.merge(originTimelineOptions, {
                            dataset: {
                                source: _.chain(bilibiliVoteVue.$data.top50OfficialValidVotesCount)
                                    .orderBy('officialValidCount')
                                    .takeRight(10)
                                    .map((officialCount) => {
                                        return {
                                            voteFor: bilibiliVoteVue.formatCandidateNameByID(officialCount.voteFor),
                                            officialValidCount: officialCount.officialValidCount
                                        };
                                    })
                                    .value()
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

                        let timelineRanges = _.chain(ajaxData).map('endTime').uniq().sortBy().map((i) => moment.unix(i).format()).value();
                        timelineRanges.push('2019-03-11T18:26:40+08:00'); // official votes count show time
                        candidatesTimelineChart.setOption({
                            baseOption: {
                                timeline: {
                                    autoPlay: true,
                                    data: timelineRanges
                                }
                            },
                            options
                        });

                        // only display official votes count legend when timeline arrive it's show time
                        candidatesTimelineChart.on('timelinechanged', (params) => {
                            if (params.currentIndex + 1 === candidatesTimelineChart.getOption().timeline[0].data.length) {
                                candidatesTimelineChart.dispatchAction({
                                    type: 'legendSelect',
                                    name: '贴吧官方统计有效票'
                                });
                            } else {
                                candidatesTimelineChart.dispatchAction({
                                    type: 'legendUnSelect',
                                    name: '贴吧官方统计有效票'
                                });
                            }
                        });
                    })
                    .always(() => candidatesTimelineChartDOM.removeClass('loading'));
            });
        };

        const bilibiliVoteVue = new Vue({
            el: '#bilibiliVote',
            data: {
                $$getTiebaUserLink,
                statsQuery: {
                    candidateCountByTimeTimeRange: 'hour',
                    countByTimeTimeRange: 'hour'
                },
                candidatesName: [],
                top50OfficialValidVotesCount: [],
                candidatesDetailColumns: [
                    {
                        title: '#',
                        dataIndex: 'candidateIndex',
                        sorter: (a, b) => a.candidateIndex - b.candidateIndex
                    },
                    {
                        title: '候选人',
                        dataIndex: 'candidateName',
                        scopedSlots: { customRender: 'candidateName' },
                        sorter: (a, b) => ([a.candidateName, b.candidateName].sort().indexOf(a.candidateName) - 1) || 1 // convert 0 to 1 for using Array.sort() to sort strings
                    },
                    {
                        title: '有效票数',
                        dataIndex: 'validCount',
                        sorter: (a, b) => a.validCount - b.validCount,
                        defaultSortOrder: 'descend'
                    },
                    {
                        title: '无效票数',
                        dataIndex: 'invalidCount',
                        sorter: (a, b) => a.invalidCount - b.invalidCount
                    },
                    {
                        title: '官方有效票数（仅前50）',
                        dataIndex: 'officialValidCount',
                        sorter: (a, b) => (a.officialValidCount || 0) - (b.officialValidCount || 0)
                    }
                ],
                candidatesDetailData: []
            },
            watch: {
                'statsQuery.candidateCountByTimeTimeRange': function (candidateCountByTimeTimeRange) {
                    // fully refresh to regenerate a new echarts instance
                    top5CandidatesCountByTimeChart.clear();
                    initialTop5CandidatesCountByTimeChart();
                    top5CandidatesCountByTimeChartDOM.addClass('loading');
                    loadTop5CandidatesCountByTimeChart(candidateCountByTimeTimeRange);
                },
                'statsQuery.countByTimeTimeRange': function (countByTimeTimeRange) {
                    // fully refresh to regenerate a new echarts instance
                    countByTimeChart.clear();
                    initialCountByTimeChart();
                    countByTimeChartDOM.addClass('loading');
                    loadCountByTimeChart(countByTimeTimeRange);
                }
            },
            mounted () {
                $.getJSON(`${$$baseUrl}/api/bilibiliVote/candidatesName.json`).done((ajaxData) => {
                    this.$data.candidatesName = ajaxData;
                    this.$data.candidatesDetailData = _.map(ajaxData, (candidateName, candidateIndex) => {
                        candidateIndex += 1;
                        return {
                            key: candidateIndex,
                            candidateIndex,
                            candidateName
                        };
                    });

                    $$reCAPTCHACheck().then((reCAPTCHAToken) => {
                        $.getJSON(`${$$baseUrl}/api/bilibiliVote/allCandidatesVotesCount`, $.param(reCAPTCHAToken)).done((ajaxData) => {
                            this.$data.candidatesDetailData = _.map(this.$data.candidatesDetailData, (candidate) => {
                                let candidateVotes = _.filter(ajaxData, { voteFor: candidate.candidateIndex.toString() });
                                if (candidateVotes != null) {
                                    let validCount = _.find(candidateVotes, { isValid: 1 });
                                    validCount = validCount == null ? 0 : validCount.count;
                                    let invalidCount = _.find(candidateVotes, { isValid: 0 });
                                    invalidCount = invalidCount == null ? 0 : invalidCount.count;
                                    return _.merge(candidate, {
                                        validCount,
                                        invalidCount
                                    });
                                }
                            });
                        });
                    });

                    $.getJSON(`${$$baseUrl}/api/bilibiliVote/top50OfficialValidVotesCount.json`).done((ajaxData) => {
                        this.$data.top50OfficialValidVotesCount = ajaxData;
                        this.$data.candidatesDetailData = _.values(_.merge( // merge by key
                            _.keyBy(this.$data.candidatesDetailData, 'candidateIndex'),
                            _.keyBy(_.map(ajaxData, (candidate) => {
                                return {
                                    candidateIndex: candidate.voteFor,
                                    officialValidCount: candidate.officialValidCount
                                };
                            }), 'candidateIndex')
                        ));
                    });

                    initialTop50CandidatesCountChart();
                    loadTop50CandidatesCountChart();
                    initialTop5CandidatesCountByTimeChart();
                    loadTop5CandidatesCountByTimeChart(this.$data.statsQuery.candidateCountByTimeTimeRange);
                    initialCountByTimeChart();
                    loadCountByTimeChart(this.$data.statsQuery.countByTimeTimeRange);
                    initialCandidatesTimelineChart();
                    loadCandidatesTimelineChart();
                });
            },
            methods: {
                formatCandidateNameByID (id) {
                    return `${id}号\n${this.$data.candidatesName[id - 1]}`;
                }
            }
        });
    </script>
@endsection
