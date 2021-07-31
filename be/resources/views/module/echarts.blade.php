@section('style-module')
    @parent
    <style>
        .echarts.loading {
            background: url({{ asset('img/icon-huaji-loading-spinner.gif') }}) no-repeat center;
        }
    </style>
@endsection

@section('script-module')
    @parent
    <script src="https://cdn.jsdelivr.net/npm/echarts@4.1.0/dist/echarts.min.js"></script>
    <script>
        'use strict';

        // resize all echarts instance when viewport size changed
        $(window).on('resize', _.throttle(() => {
            $('.echarts').each((k, echartsDom) => {
                let echartsInstance = echarts.getInstanceByDom(echartsDom);
                if (echartsInstance !== undefined) { // instance might be undefined when echarts haven't been initialed
                    echartsInstance.resize();
                }
            });
        }, 1000, { leading: false }));

        const $$echartsTimeRangeAxisType = {
            minute: 'time',
            hour: 'time',
            day: 'time',
            week: 'category',
            month: 'category',
            year: 'category'
        };

        const $$echartsTimeRangeAxisPointerLabelFormatter = {
            minute: (param) => moment(param.value).format('YYYY-MM-DD HH:mm'),
            hour: (param) => moment(param.value).format('YYYY-MM-DD HH:00~HH:60'),
            day: (param) => moment(param.value).format('YYYY-MM-DD'),
            week: (param) => param.value,
            month: (param) => param.value,
            year: (param) => param.value
        };
    </script>
@endsection
