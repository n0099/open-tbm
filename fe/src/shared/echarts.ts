import type { BarSeriesOption, LineSeriesOption } from 'echarts/charts';
import * as echarts from 'echarts/core';
import _ from 'lodash';
import { DateTime } from 'luxon';

window.addEventListener('resize', _.throttle(() => {
    document.querySelectorAll<HTMLElement>('.echarts').forEach(echartsDom => {
        // https://github.com/apache/echarts/issues/15896
        // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
        echarts.getInstanceByDom(echartsDom)?.resize();
    });
}, 200, { leading: false }));

export const emptyChartSeriesData = (chart: echarts.ECharts) => {
    chart.setOption({
        series: _.map(chart.getOption().series as BarSeriesOption | LineSeriesOption, series => {
            if (typeof series === 'object' && 'data' in series) series.data = [];
            return series;
        })
    });
};

const timeGranularList = ['minute', 'hour', 'day', 'week', 'month', 'year'];
type TimeGranularList = 'day' | 'hour' | 'minute' | 'month' | 'week' | 'year';
export const timeGranularAxisType: { [K in TimeGranularList]: 'category' | 'time' } = {
    minute: 'time',
    hour: 'time',
    day: 'time',
    week: 'category',
    month: 'category',
    year: 'category'
};

type SqlDateTimeUtc8 = string;
const getLuxonFromDateTimeUTC8 = (dateTime: SqlDateTimeUtc8) => DateTime.fromSQL(dateTime, { zone: 'Asia/Shanghai' });
const stringTypeGuard = (p: unknown): p is string => (typeof p === 'string' ? true : '');
export const timeGranularAxisPointerLabelFormatter: { [K in TimeGranularList]: (params: { value: Date | number | string }) => string } = {
    minute: ({ value }) => (typeof value === 'number' ? DateTime.fromMillis(value).toLocaleString(DateTime.DATETIME_SHORT) : ''),
    hour: ({ value }) => (typeof value === 'number' ? DateTime.fromMillis(value).toLocaleString({ year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric' }) : ''),
    day: ({ value }) => (typeof value === 'number' ? DateTime.fromMillis(value).toLocaleString(DateTime.DATE_MED_WITH_WEEKDAY) : ''),
    week: ({ value }) => (typeof value === 'string' ? value : ''),
    month: ({ value }) => (typeof value === 'string' ? value : ''),
    year: ({ value }) => (typeof value === 'string' ? value : '')
};
