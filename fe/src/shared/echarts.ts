import type { BarSeriesOption, LineSeriesOption } from 'echarts/charts';
import type { ToolboxComponentOption } from 'echarts/components';
import type { ColorPaletteOptionMixin } from 'echarts/types/src/util/types';
import * as echarts from 'echarts/core';
import _ from 'lodash';
import { DateTime } from 'luxon';

window.addEventListener('resize', _.throttle(() => {
    document.querySelectorAll<HTMLElement>('.echarts').forEach(echartsDom => {
        echarts.getInstanceByDom(echartsDom)?.resize();
    });
}, 200, { leading: false }));

export const echarts4ColorThemeFallback: ColorPaletteOptionMixin = {
    color: [
        '#c23531',
        '#2f4554',
        '#61a0a8',
        '#d48265',
        '#91c7ae',
        '#749f83',
        '#ca8622',
        '#bda29a',
        '#6e7074',
        '#546570',
        '#c4ccd3'
    ]
};

export const commonToolboxFeatures: echarts.ComposeOption<ToolboxComponentOption> = {
    toolbox: {
        feature: {
            dataZoom: { show: true, yAxisIndex: 'none' },
            saveAsImage: { show: true }
        }
    }
};
export const extendCommonToolbox
    = (extend: echarts.ComposeOption<ToolboxComponentOption>): echarts.ComposeOption<ToolboxComponentOption> =>
        _.merge(commonToolboxFeatures, extend);

export const emptyChartSeriesData = (chart: echarts.ECharts) => {
    chart.setOption({
        series: _.map(chart.getOption().series as BarSeriesOption | LineSeriesOption, series => {
            if (typeof series === 'object' && 'data' in series)
                series.data = [];
            return series;
        })
    });
};

export const timeGranularities = ['minute', 'hour', 'day', 'week', 'month', 'year'] as const;
export type TimeGranularities = typeof timeGranularities[number];
export type TimeGranularitiesStringMap = { [P in TimeGranularities]?: string };
export const timeGranularityAxisType: { [P in TimeGranularities]: 'category' | 'time' } = {
    minute: 'time',
    hour: 'time',
    day: 'time',
    week: 'category',
    month: 'category',
    year: 'category'
};
export const timeGranularityAxisPointerLabelFormatter: { [P in TimeGranularities]: (params: { value: Date | number | string }) => string } = {
    minute: ({ value }) =>
        (_.isNumber(value) ? DateTime.fromMillis(value).toLocaleString(DateTime.DATETIME_SHORT) : ''),
    hour: ({ value }) =>
        (_.isNumber(value)
            ? DateTime.fromMillis(value).toLocaleString(
                { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric' }
            )
            : ''),
    day: ({ value }) =>
        (_.isNumber(value) ? DateTime.fromMillis(value).toLocaleString(DateTime.DATE_MED_WITH_WEEKDAY) : ''),
    week: ({ value }) => (_.isString(value) ? value : ''),
    month: ({ value }) => (_.isString(value) ? value : ''),
    year: ({ value }) => (_.isString(value) ? value : '')
};
