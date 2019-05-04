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
            $('.echarts').each((k, echartsDOM) => {
                let echartsInstance = echarts.getInstanceByDom(echartsDOM);
                if (echartsInstance != null) { // instance might be undefined when echarts haven't been initialed
                    echartsInstance.resize();
                }
            });
        }, 1000, { leading: false }));
    </script>
@endsection