<template>
    <div class="mt-2" id="bilibiliVote">
        <small>本页上所有时间均为UTC+8时间</small>
        <p>有效票定义：</p>
        <ul>
            <li>投票人吧内等级大于4</li>
            <li><del>投票者回复内本人ID（xxx投yyy中xxx）与百度ID（非昵称）一致</del></li>
            <li>被投候选人序号有效（1~1056）</li>
            <li>此前未有过有效投票（即改票）</li>
        </ul>
        <p><a href="https://github.com/n0099/bilibiliVote" target="_blank">原始数据 @ GitHub</a></p>
        <p><a href="https://web.archive.org/web/20190312014057/https://tieba.baidu.com/p/6059516291" target="_blank">关于启动本吧吧主招募的公示</a></p>
        <p><a href="https://web.archive.org/web/20190312014145/https://tieba.baidu.com/p/6062186860" target="_blank">【吧主招募】bilibili吧吧主候选人吧友投票贴</a></p>
        <p><a href="https://web.archive.org/web/20190312014242/https://tieba.baidu.com/p/6063655698" target="_blank">Bilibili吧吧主招募投票结果公示</a></p>
        <p><a href="https://web.archive.org/web/0/https://tieba.baidu.com/p/6061937239" target="_blank">吧务候选名单详细数据，含精品数（截止3月10日00时15分）</a></p>
        <p><a href="https://web.archive.org/web/0/https://tieba.baidu.com/p/6062515014" target="_blank">B吧吧主候选人 支持率Top10 含支持者等级分布</a></p>
        <p><a href="https://web.archive.org/web/0/https://tieba.baidu.com/p/6062736510" target="_blank">【数据分享】炎魔 五娃 奶茶的支持者都关注哪些贴吧？</a></p>
        <p><a href="https://web.archive.org/web/0/https://tieba.baidu.com/p/6063625612" target="_blank">bilibili吧 吧主候选人支持率Top20（非官方数据，仅供参考）</a></p>
        <p><a href="https://www.bilibili.com/video/av46507371" target="_blank">【数据可视化】一分钟看完bilibili吧吧主公投</a></p>
        <hr />
        <div ref="top50CandidateCountRef" class="echarts" id="top50CandidateCount" />
        <hr />
        <div ref="top10CandidatesTimelineRef" class="echarts" id="top10CandidatesTimeline" />
        <hr />
        <div class="row justify-content-end">
            <label class="col-2 col-form-label text-end" for="top5CandidateCountGroupByTimeGranularity">时间粒度</label>
            <div class="col-2">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="calendar-alt" /></span>
                    <TimeGranularity v-model="query.top5CandidateCountGroupByTimeGranularity"
                                     :granularities="['minute', 'hour']"
                                     id="top5CandidateCountGroupByTimeGranularity" />
                </div>
            </div>
        </div>
        <div ref="top5CandidateCountGroupByTimeRef" class="echarts" id="top5CandidateCountGroupByTime" />
        <hr />
        <div class="row justify-content-end">
            <label class="col-2 col-form-label text-end" for="allVoteCountGroupByTimeGranularity">时间粒度</label>
            <div class="col-2">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="clock" /></span>
                    <TimeGranularity v-model="query.allVoteCountGroupByTimeGranularity"
                                     :granularities="['minute', 'hour']"
                                     id="allVoteCountGroupByTimeGranularity" />
                </div>
            </div>
        </div>
        <div ref="allVoteCountGroupByTimeRef" class="echarts" id="allVoteCountGroupByTime" />
        <hr />
        <Table :columns="candidatesDetailColumns"
               :data-source="candidatesDetailData"
               :pagination="{ pageSize: 50, pageSizeOptions: ['20', '50', '100', '200', '1056'] }"
               rowKey="candidateIndex">
            <template #bodyCell="{ column: { dataIndex: column }, value: name }">
                <template v-if="column === 'candidateName'">
                    <a :href="toTiebaUserProfileUrl({ name })">{{ name }}</a>
                </template>
            </template>
        </Table>
    </div>
</template>

<script setup lang="ts">
import TimeGranularity from '@/components/widgets/TimeGranularity.vue';

import type { GroupByTimeGranularity, IsValid, Top10CandidatesTimeline } from '@/api/bilibiliVote';
import { json } from '@/api/bilibiliVote';
import type { ObjUnknown } from '@/shared';
import { titleTemplate, toTiebaUserProfileUrl } from '@/shared';
import { echarts4ColorTheme, timeGranularityAxisPointerLabelFormatter, timeGranularityAxisType } from '@/shared/echarts';

import { onMounted, ref, watch } from 'vue';
import { Table } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { useHead } from '@unhead/vue';
import { DateTime } from 'luxon';
import * as _ from 'lodash';

import type { BarSeriesOption, LineSeriesOption, PieSeriesOption } from 'echarts/charts';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import type { AxisPointerComponentOption, DataZoomComponentOption, DatasetComponentOption, GraphicComponentOption, GridComponentOption, LegendComponentOption, MarkLineComponentOption, TimelineComponentOption, TitleComponentOption, ToolboxComponentOption, TooltipComponentOption } from 'echarts/components';
import { DataZoomComponent, DatasetComponent, GraphicComponent, GridComponent, LegendComponent, MarkLineComponent, TimelineComponent, TitleComponent, ToolboxComponent, TooltipComponent } from 'echarts/components';
import * as echarts from 'echarts/core';
import { LabelLayout } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';
import type { TimelineChangePayload } from 'echarts/types/src/component/timeline/timelineAction';
import type { OptionDataItem } from 'echarts/types/src/util/types';

echarts.use([BarChart, CanvasRenderer, DataZoomComponent, DatasetComponent, GraphicComponent, GridComponent, LabelLayout, LegendComponent, MarkLineComponent, LineChart, PieChart, TimelineComponent, TitleComponent, ToolboxComponent, TooltipComponent]);

interface CandidateVoteCount { officialValidCount: number | null, validCount: number, invalidCount: number }
type CandidatesDetailData = Array<CandidateVoteCount & { candidateIndex: number, candidateName: string }>;
const candidatesDetailColumns: Array<ObjUnknown & {
    title: string,
    dataIndex: string,
    sorter: (a: CandidatesDetailData[0], b: CandidatesDetailData[0]) => number
}> = [{
    title: '#',
    dataIndex: 'candidateIndex',
    sorter: (a, b) => a.candidateIndex - b.candidateIndex
}, {
    title: '候选人',
    dataIndex: 'candidateName',
    sorter: (a, b) => a.candidateName.localeCompare(b.candidateName)
}, {
    title: '有效票数',
    dataIndex: 'validCount',
    sorter: (a, b) => a.validCount - b.validCount,
    defaultSortOrder: 'descend'
}, {
    title: '无效票数',
    dataIndex: 'invalidCount',
    sorter: (a, b) => a.invalidCount - b.invalidCount
}, {
    title: '官方有效票数（仅前50）',
    dataIndex: 'officialValidCount',
    sorter: (a, b) => (a.officialValidCount ?? 0) - (b.officialValidCount ?? 0)
}];

type ChartName = keyof typeof chartElements;
const chartElements = {
    top50CandidateCount: ref<HTMLElement>(),
    top10CandidatesTimeline: ref<HTMLElement>(),
    top5CandidateCountGroupByTime: ref<HTMLElement>(),
    allVoteCountGroupByTime: ref<HTMLElement>()
};

// template ref
const {
    top50CandidateCount: top50CandidateCountRef,
    top10CandidatesTimeline: top10CandidatesTimelineRef,
    top5CandidateCountGroupByTime: top5CandidateCountGroupByTimeRef,
    allVoteCountGroupByTime: allVoteCountGroupByTimeRef
} = chartElements;
const echartsInstances: { [P in ChartName]: echarts.ECharts | null } = {
    top50CandidateCount: null,
    top10CandidatesTimeline: null,
    top5CandidateCountGroupByTime: null,
    allVoteCountGroupByTime: null
};

let top10CandidatesTimelineVotes: { [P in 'invalid' | 'valid']: Top10CandidatesTimeline } = { valid: [], invalid: [] };
type Top10CandidatesTimelineDataset = Array<CandidateVoteCount & { voteFor: string }>;
interface VoteCountSeriesLabelFormatterParams {
    data: OptionDataItem | Top10CandidatesTimelineDataset[0],
    name: string
}
const isCandidatesDetailData = (p: unknown): p is Top10CandidatesTimelineDataset[0] =>
    _.isObject(p) && 'officialValidCount' in p && 'validCount' in p && 'invalidCount' in p && 'voteFor' in p;
const voteCountSeriesLabelFormatter = (
    votesData: Top10CandidatesTimeline,
    currentCount: number,
    candidateIndex: string
) => {
    const [timeline] = echartsInstances.top10CandidatesTimeline?.getOption()
        .timeline as [{ data: number[], currentIndex: number }];
    const previousTimelineValue = _.find(votesData, {
        endTime: timeline.data[timeline.currentIndex - 1],
        voteFor: Number(candidateIndex.slice(0, Math.max(0, candidateIndex.indexOf('号')))) // trim trailing '号' in series name
    });

    return `${currentCount} (+${currentCount - (previousTimelineValue?.count ?? 0)})`;
};

const sourceAttribution = `来源：open-tbm @ ${import.meta.env.VITE_INSTANCE_NAME}`;
type ChartOptionTop10CandidatesTimeline =
    echarts.ComposeOption<BarSeriesOption | GraphicComponentOption | GridComponentOption | LegendComponentOption | PieSeriesOption | TimelineComponentOption | TitleComponentOption | ToolboxComponentOption | TooltipComponentOption>;
const chartsInitialOption: {
    top50CandidateCount: echarts.ComposeOption<BarSeriesOption | DataZoomComponentOption | GridComponentOption | LegendComponentOption | TitleComponentOption | ToolboxComponentOption | TooltipComponentOption>,
    top10CandidatesTimeline: ChartOptionTop10CandidatesTimeline,
    top5CandidateCountGroupByTime: echarts.ComposeOption<DataZoomComponentOption | GridComponentOption | LegendComponentOption | TitleComponentOption | TooltipComponentOption>,
    allVoteCountGroupByTime: echarts.ComposeOption<DataZoomComponentOption | GridComponentOption | LegendComponentOption | LineSeriesOption | TitleComponentOption | ToolboxComponentOption | TooltipComponentOption>
} = {
    top50CandidateCount: {
        title: {
            text: 'bilibili吧吧主公投 前50候选人票数',
            subtext: `候选人间线上数字为与前一人票差 数据仅供参考 ${sourceAttribution}`
        },
        axisPointer: { link: [{ xAxisIndex: 'all' }] },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        toolbox: {
            feature: {
                dataZoom: { show: true, yAxisIndex: 'none' },
                restore: { show: true },
                saveAsImage: { show: true }
            }
        },
        legend: { left: '30%' },
        dataZoom: [{
            type: 'slider',
            xAxisIndex: [0, 1],
            startValue: 0,
            endValue: 7
        }, {
            type: 'inside',
            xAxisIndex: [0, 1]
        }],
        grid: [
            { height: '40%' },
            { height: '28%', top: '65%' }
        ],
        xAxis: [{
            type: 'category',
            axisLabel: { rotate: 30, margin: 14 }
        }, {
            type: 'category',
            axisLabel: { show: false },
            gridIndex: 1,
            position: 'top'
        }],
        yAxis: [{
            type: 'value',
            splitLine: { show: false },
            splitArea: { show: true }
        }, {
            type: 'value',
            splitLine: { show: false },
            splitArea: { show: true },
            inverse: true,
            gridIndex: 1,
            min: 4.5 // _.minBy(top50CandidatesVoteCount, 'voterAvgGrade') = 5
        }],
        series: [{
            id: 'officialValidCount',
            name: '贴吧官方统计有效票',
            type: 'bar',
            encode: { x: 'voteFor', y: 'officialValidCount' }
        }, {
            id: 'validCount',
            name: '有效票',
            type: 'bar',
            label: { show: true, position: 'top', color: '#fe980e' },
            encode: { x: 'voteFor', y: 'validCount' }
        }, {
            id: 'invalidCount',
            name: '无效票',
            type: 'bar',
            z: 0,
            barGap: '0%',
            encode: { x: 'voteFor', y: 'invalidCount' }
        }, {
            id: 'validVotesVoterAvgGrade',
            name: '有效投票者平均等级',
            type: 'bar',
            xAxisIndex: 1,
            yAxisIndex: 1,
            encode: { x: 'voteFor', y: 'validAvgGrade' },
            markLine: { data: [{ type: 'average', name: '窗口内平均有效投票者平均等级' }] }
        }, {
            id: 'invalidVotesVoterAvgGrade',
            name: '无效投票者平均等级',
            type: 'bar',
            xAxisIndex: 1,
            yAxisIndex: 1,
            barGap: '0%',
            encode: { x: 'voteFor', y: 'invalidAvgGrade' },
            markLine: { data: [{ type: 'average', name: '窗口内平均无均投票者平均等级' }] }
        }]
    },
    top10CandidatesTimeline: {
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
                subtext: `候选人间线上数字为与前一人票差\n数据仅供参考 ${sourceAttribution}`
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow', z: -1 }
            },
            toolbox: {
                feature: {
                    dataZoom: { show: true, yAxisIndex: 'none' },
                    restore: { show: true },
                    saveAsImage: { show: true }
                }
            },
            legend: { data: ['贴吧官方统计有效票', '有效票', '无效票'] },
            xAxis: {
                type: 'value',
                splitLine: { show: false },
                splitArea: { show: true }
            },
            yAxis: { type: 'category' },
            series: [{
                id: 'officialValidCount',
                name: '贴吧官方统计有效票',
                type: 'bar',
                label: {
                    show: true,
                    position: 'right',
                    formatter: (p: VoteCountSeriesLabelFormatterParams) => (isCandidatesDetailData(p.data)
                        ? `${p.data.officialValidCount} 相差${(p.data.officialValidCount ?? 0) - p.data.validCount}`
                        : '')
                },
                encode: { x: 'officialValidCount', y: 'voteFor' },
                itemStyle: { color: '#91c7ae' }
            }, {
                id: 'validCount',
                name: '有效票',
                type: 'bar',
                label: {
                    show: true,
                    position: 'right',
                    color: '#fe980e',
                    formatter: (p: VoteCountSeriesLabelFormatterParams) => (isCandidatesDetailData(p.data)
                        ? voteCountSeriesLabelFormatter(top10CandidatesTimelineVotes.valid, p.data.validCount, p.name)
                        : '')
                },
                z: 1, // prevent the label covered by invalidCount category
                encode: { x: 'validCount', y: 'voteFor' },
                itemStyle: { color: '#c23531' }
            }, {
                id: 'invalidCount',
                name: '无效票',
                type: 'bar',
                label: {
                    show: true,
                    position: 'right',
                    color: '#999999',
                    formatter: (p: VoteCountSeriesLabelFormatterParams) => (isCandidatesDetailData(p.data)
                        ? voteCountSeriesLabelFormatter(
                            top10CandidatesTimelineVotes.invalid,
                            p.data.invalidCount,
                            p.name
                        )
                        : '')
                },
                z: 0,
                barGap: '0%',
                encode: { x: 'invalidCount', y: 'voteFor' },
                itemStyle: { color: '#2f4554' }
            }, {
                id: 'totalVotesValidation',
                type: 'pie',
                center: ['85%', '58%'],
                radius: ['25%', '8%'],
                label: { show: true, position: 'inside', formatter: '{b}\n{c}\n{d}%' }
            }],
            graphic: {
                type: 'text',
                right: '10%',
                bottom: '15%',
                style: { fill: '#989898', align: 'right', font: '1.75rem "Microsoft YaHei"' }
            }
        }
    },
    top5CandidateCountGroupByTime: {
        title: {
            text: 'bilibili吧吧主公投 前5票数分时增量',
            subtext: `数据仅供参考 ${sourceAttribution}`
        },
        axisPointer: { link: [{ xAxisIndex: 'all' }] },
        tooltip: { trigger: 'axis' },
        legend: { type: 'scroll', left: '30%' },
        dataZoom: [{
            type: 'slider',
            xAxisIndex: [0, 1],
            end: 100,
            bottom: '46%'
        }, {
            type: 'inside',
            xAxisIndex: [0, 1]
        }],
        grid: [
            { height: '35%' },
            { height: '35%', top: '60%' }
        ],
        xAxis: [{
            type: 'time'
        }, {
            type: 'time',
            gridIndex: 1,
            position: 'top'
        }],
        yAxis: [
            { type: 'value' },
            { type: 'value', gridIndex: 1 }
        ]
    },
    allVoteCountGroupByTime: {
        title: {
            text: 'bilibili吧吧主公投 总票数分时增量',
            subtext: `数据仅供参考 ${sourceAttribution}`
        },
        tooltip: { trigger: 'axis' },
        toolbox: {
            feature: {
                dataZoom: { show: true, yAxisIndex: 'none' },
                restore: { show: true },
                saveAsImage: { show: true },
                magicType: { show: true, type: ['stack', 'line', 'bar'] }
            }
        },
        legend: { left: '30%' },
        dataZoom: [{
            type: 'slider',
            xAxisIndex: 0,
            end: 100
        }, {
            type: 'inside',
            xAxisIndex: 0
        }],
        xAxis: { type: 'time' },
        yAxis: { type: 'value' },
        series: [{
            id: 'validCount',
            name: '有效票增量',
            type: 'line',
            symbolSize: 2,
            smooth: true,
            encode: { x: 'time', y: 'validCount' }
        }, {
            id: 'invalidCount',
            name: '无效票增量',
            type: 'line',
            symbolSize: 2,
            smooth: true,
            encode: { x: 'time', y: 'invalidCount' }
        }]
    }
};

useHead({ title: titleTemplate('bilibili吧2019年吧主公投 - 专题') });
const query = ref<{
    top5CandidateCountGroupByTimeGranularity: GroupByTimeGranularity,
    allVoteCountGroupByTimeGranularity: GroupByTimeGranularity
}>({
    top5CandidateCountGroupByTimeGranularity: 'hour',
    allVoteCountGroupByTimeGranularity: 'hour'
});
const candidatesDetailData = ref<CandidatesDetailData>([]);

interface Coord { coord: [number, number] }
type DiffWithPreviousMarkLineFormatter =
    Array<[Coord & { label: { show: true, position: 'middle', formatter: string } }, Coord]>;
const findVoteCount = (votes: Array<{ isValid: IsValid, count: number }>, isValid: IsValid) =>
    _.find(votes, { isValid })?.count ?? 0;
const formatCandidateName = (id: number) => `${id}号\n${json.candidateNames[id - 1]}`;

const loadCharts = {
    top50CandidateCount: () => {
        // [{ voteFor: '1号', validVotes: 1, validAvgGrade: 18, invalidVotes: 1, invalidAvgGrade: 18 }, ... ]
        const dataset = _.chain(json.top50CandidatesVoteCount)
            .groupBy('voteFor')

            // sort grouped candidate by its descending total votes count
            .sortBy(candidate => -_.sumBy(candidate, 'count'))
            .map(candidateVotes => {
                const validVotes = _.find(candidateVotes, { isValid: 1 });
                const invalidVotes = _.find(candidateVotes, { isValid: 0 });
                const officialValidCount = _.find(json.top50CandidatesOfficialValidVoteCount,
                    { voteFor: candidateVotes[0].voteFor })
                    ?.officialValidCount ?? 0;

                return {
                    voteFor: formatCandidateName(candidateVotes[0].voteFor),
                    validCount: validVotes?.count ?? 0,
                    validAvgGrade: validVotes?.voterAvgGrade ?? 0,
                    invalidCount: invalidVotes?.count ?? 0,
                    invalidAvgGrade: invalidVotes?.voterAvgGrade ?? 0,
                    officialValidCount
                };
            })
            .value();

        const validCount = _.map(dataset, 'validCount');
        const validCountDiffWithPrevious: DiffWithPreviousMarkLineFormatter = validCount.map((count, index) => [{
            label: {
                show: true,
                position: 'middle',
                formatter: (-(count - validCount[index - 1])).toString()
            },
            coord: [index, count]
        }, { coord: [index - 1, validCount[index - 1]] }]);
        validCountDiffWithPrevious.shift(); // first candidate doesn't need to exceed anyone

        echartsInstances.top50CandidateCount?.setOption({
            dataset: { source: dataset },
            series: [{
                id: 'validCount',
                markLine: {
                    lineStyle: { type: 'dashed' },
                    symbol: 'none',
                    data: validCountDiffWithPrevious
                }
            }]
        } as echarts.ComposeOption<DatasetComponentOption | LineSeriesOption | MarkLineComponentOption>);
    },
    top10CandidatesTimeline: () => {
        top10CandidatesTimelineVotes = {
            valid: _.filter(json.top10CandidatesTimeline, { isValid: 1 }),
            invalid: _.filter(json.top10CandidatesTimeline, { isValid: 0 })
        };

        const options: ChartOptionTop10CandidatesTimeline[] = [];
        _.each(_.groupBy(json.top10CandidatesTimeline, 'endTime'), (timeGroup, time) => {
            // [{ voteFor: formatCandidateName(1), validCount: 1, invalidCount: 0, officialValidCount: null },...]
            const dataset: Top10CandidatesTimelineDataset = _.chain(timeGroup)
                .sortBy('count')
                .groupBy('voteFor')
                .sortBy(group => _.chain(group).map('count').sum().value())
                .map(candidateVotes => ({
                    voteFor: formatCandidateName(candidateVotes[0].voteFor),
                    validCount: findVoteCount(candidateVotes, 1),
                    invalidCount: findVoteCount(candidateVotes, 0),
                    officialValidCount: null
                }))
                .value();

            const validCount = _.map(dataset, 'validCount');
            const validCountDiffWithPrevious: DiffWithPreviousMarkLineFormatter =
                (validCount.map((count, index) => [
                    {
                        label: {
                            show: true,
                            position: 'middle',
                            formatter: (-(count - validCount[index + 1])).toString()
                        },
                        coord: [count, index]
                    }, { coord: [validCount[index + 1], index + 1] }
                ]) as DiffWithPreviousMarkLineFormatter).slice(-5, -1); // only top 5

            const getVotesTotalCount = (isValid?: IsValid) => _.chain(timeGroup)
                .filter(isValid === undefined ? () => true : { isValid })
                .map('count').sum().value();

            options.push({
                dataset: { source: dataset },
                series: [{
                    id: 'validCount',
                    markLine: {
                        lineStyle: { type: 'dashed' },
                        symbol: 'none',
                        data: validCountDiffWithPrevious
                    }
                }, {
                    id: 'totalVotesValidation',
                    data: [
                        { name: '有效票', value: getVotesTotalCount(1) },
                        { name: '无效票', value: getVotesTotalCount(0) }
                    ]
                }],
                graphic: {
                    style: {
                        fill: '#989898',
                        align: 'right',
                        font: '1.75rem "Microsoft YaHei"',
                        text: `共${getVotesTotalCount()}票\n${DateTime.fromSeconds(Number(time)).toLocaleString({
                            month: 'short',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                            timeZone: 'Asia/Shanghai'
                        })}`
                    }
                }
            });
        });

        // clone last timeline option then transform it to official votes count option
        const originalTimelineOptions = _.cloneDeep(options.at(-1));
        if (originalTimelineOptions === undefined || !_.isArray(originalTimelineOptions.series))
            return;
        _.remove(originalTimelineOptions.series, { id: 'totalVotesValidation' });
        options.push(_.merge(originalTimelineOptions, { // deep merge
            dataset: {
                source: _.chain(json.top50CandidatesOfficialValidVoteCount)
                    .orderBy('officialValidCount')
                    .takeRight(10)
                    .map(({ voteFor, officialValidCount }) => ({
                        voteFor: formatCandidateName(voteFor),
                        officialValidCount
                    }))
                    .value()
            },
            series: [...originalTimelineOptions.series, {
                id: 'totalVotesValidation',
                data: [
                    { name: '官方有效票', value: 12247 },
                    { name: '官方无效票', value: 473 }
                ]
            }],
            graphic: {
                style: {
                    fill: '#989898',
                    textAlign: 'right',
                    font: '1.75rem "Microsoft YaHei"',
                    text: '贴吧官方统计共12720票\n有效12247票 无效473票\n3月11日 18:26'
                }
            }
        }));

        const timelineRanges = _.chain(json.top10CandidatesTimeline).map('endTime').sort().sortedUniq().value();
        timelineRanges.push(1552292800); // 2019-03-11T18:26:40+08:00 is the showtime of official votes count
        echartsInstances.top10CandidatesTimeline?.setOption<ChartOptionTop10CandidatesTimeline>({
            baseOption: { timeline: { autoPlay: true, data: timelineRanges } },
            options
        });

        // only display official votes count legend when timeline arrive its showtime
        echartsInstances.top10CandidatesTimeline?.on('timelinechanged', params => {
            echartsInstances.top10CandidatesTimeline?.dispatchAction({
                type: (params as TimelineChangePayload).currentIndex + 1 === timelineRanges.length
                    ? 'legendSelect'
                    : 'legendUnSelect',
                name: '贴吧官方统计有效票'
            });
        });
    },
    top5CandidateCountGroupByTime: () => {
        const timeGranularity = query.value.top5CandidateCountGroupByTimeGranularity;
        const top5CandidateCountGroupByTime = timeGranularity === 'minute'
            ? json.top5CandidatesVoteCountGroupByMinute
            : json.top5CandidatesVoteCountGroupByHour;
        const top5CandidatesIndex = _.chain(top5CandidateCountGroupByTime)
            .filter({ isValid: 1 }).map('voteFor').sort().sortedUniq().value(); // not order by votes count
        const validVotes = _.filter(top5CandidateCountGroupByTime, { isValid: 1 });
        const invalidVotes = _.filter(top5CandidateCountGroupByTime, { isValid: 0 });
        const series: LineSeriesOption[] = [];
        top5CandidatesIndex.forEach(candidateIndex => {
            series.push({
                name: `${candidateIndex}号有效票增量`,
                type: 'line',
                symbolSize: 2,
                smooth: true,
                data: _.filter(validVotes, { voteFor: candidateIndex }).map(i => [i.time, i.count])
            }, {
                name: `${candidateIndex}号无效票增量`,
                type: 'line',
                symbolSize: 2,
                smooth: true,
                xAxisIndex: 1,
                yAxisIndex: 1,
                data: _.filter(invalidVotes, { voteFor: candidateIndex }).map(i => [i.time, i.count])
            });
        });
        echartsInstances.top5CandidateCountGroupByTime?.setOption({
            axisPointer: { label: { formatter: timeGranularityAxisPointerLabelFormatter[timeGranularity] } },
            xAxis: Array.from({ length: 2 }).fill({ type: timeGranularityAxisType[timeGranularity] }),
            series
        } as echarts.ComposeOption<AxisPointerComponentOption | GridComponentOption | LineSeriesOption>);
    },
    allVoteCountGroupByTime: () => {
        const timeGranularity = query.value.allVoteCountGroupByTimeGranularity;
        const allVoteCountGroupByTime = timeGranularity === 'minute'
            ? json.allVoteCountGroupByMinute
            : json.allVoteCountGroupByHour;

        // [{ time: '2019-03-11 12:00', validCount: 1, invalidCount: 0 }, ... ]
        const dataset = _.chain(allVoteCountGroupByTime)
            .groupBy('time')
            .map((count, time) => ({
                time,
                validCount: findVoteCount(count, 1),
                invalidCount: findVoteCount(count, 0)
            }))
            .value();
        echartsInstances.allVoteCountGroupByTime?.setOption({
            axisPointer: { label: { formatter: timeGranularityAxisPointerLabelFormatter[timeGranularity] } },
            xAxis: { type: timeGranularityAxisType[timeGranularity] },
            dataset: { source: dataset }
        } as echarts.ComposeOption<DatasetComponentOption | GridComponentOption>);
    }
};

watch(() => query.value.top5CandidateCountGroupByTimeGranularity,
    () => { loadCharts.top5CandidateCountGroupByTime() });
watch(() => query.value.allVoteCountGroupByTimeGranularity,
    () => { loadCharts.allVoteCountGroupByTime() });
onMounted(() => {
    _.map(chartElements, (elRef, chartName: ChartName) => {
        if (elRef.value === undefined)
            return;
        elRef.value.classList.add('loading');
        const chart = echarts.init(elRef.value, echarts4ColorTheme);
        chart.setOption(chartsInitialOption[chartName]);
        echartsInstances[chartName] = chart;
    });
    candidatesDetailData.value = json.candidateNames.map((candidateName, index) =>
        ({ candidateIndex: index + 1, candidateName, officialValidCount: null, validCount: 0, invalidCount: 0 }));
    candidatesDetailData.value = candidatesDetailData.value.map(candidate => {
        const candidateVotes = _.filter(json.allCandidatesVoteCount, { voteFor: candidate.candidateIndex });

        return {
            ...candidate,
            validCount: findVoteCount(candidateVotes, 1),
            invalidCount: findVoteCount(candidateVotes, 0)
        };
    });

    // add candidate index as keys then deep merge will combine same keys values, finally remove keys
    candidatesDetailData.value = Object.values(_.merge(
        _.keyBy(candidatesDetailData.value, 'candidateIndex'),
        _.keyBy(json.top50CandidatesOfficialValidVoteCount.map(candidate => ({
            candidateIndex: candidate.voteFor,
            officialValidCount: candidate.officialValidCount
        })), 'candidateIndex')
    ));
    _.map(echartsInstances, (chart, chartName: ChartName) => {
        if (chart === null)
            return;
        loadCharts[chartName]();
        chartElements[chartName].value?.classList.remove('loading');
    });
});
</script>

<style scoped>
.echarts {
    margin-block-start: .5rem;
}
#top50CandidateCount {
    block-size: 32rem;
}
#top10CandidatesTimeline {
    block-size: 40rem;
}
#top5CandidateCountGroupByTime {
    block-size: 40rem;
}
#allVoteCountGroupByTime {
    block-size: 20rem;
}
</style>
