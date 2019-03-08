@php($baseUrl = env('APP_URL'))
@php($httpDomain = implode('/', array_slice(explode('/', $baseUrl), 0, 3)))
@php($baseUrlDir = substr($baseUrl, strlen($httpDomain)))
@php($reCAPTCHASiteKey = env('reCAPTCHA_SITE_KEY'))
@php($GATrackingId = env('GA_TRACKING_ID'))
<!doctype html>
<html lang="zh-cmn-Hans">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $GATrackingId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', '{{ $GATrackingId }}');
        </script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.6.3/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/noty@3.1.4/lib/noty.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.css" rel="stylesheet">
        <link href="{{ $baseUrl }}/css/bootstrap-callout.css" rel="stylesheet">
        <style>
            @media (max-width: 991.98px) {
                .container {
                    max-width: 100%;
                }
            }
            @media screen and (orientation: portrait) {
                .horizontal-mobile-message {
                    display: block
                }
            }
            @media screen and (orientation: landscape) {
                .horizontal-mobile-message {
                    display: none
                }
            }

            .horizontal-mobile-message {
                z-index: 1040;
            }

            .footer-outer {
                background-color: #2196f3;
            }
            .footer-inner {
                background-color: rgba(0,0,0,.2);
            }

            * {
                font-weight: 300;
                font-family: "Lucida Grande", "Microsoft Yahei", 'Noto Sans SC', sans-serif;
            }

            .echarts.loading {
                background: url({{ $baseUrl }}/img/icon-huaji-loading-spinner.gif) no-repeat center;
            }

            ::-webkit-scrollbar
            {
                width: 10px;
                background-color: #F5F5F5;
            }
            ::-webkit-scrollbar-track
            {
                box-shadow: inset 0 0 6px rgba(0,0,0,0.3);
                background-color: #F5F5F5;
            }
            ::-webkit-scrollbar-thumb
            {
                background-color: #F90;
                background-image: -webkit-linear-gradient(
                    45deg,
                    rgba(255, 255, 255, .2) 25%,
                    transparent 25%,
                    transparent 50%,
                    rgba(255, 255, 255, .2) 50%,
                    rgba(255, 255, 255, .2) 75%,
                    transparent 75%,
                    transparent
                )
            }
        </style>
        @yield('head-meta')
        <title>@yield('title') - 贴吧云监控</title>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light shadow-sm bg-light">
            <a class="navbar-brand" href="{{ $baseUrl }}">贴吧云监控</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbar">
                <ul class="navbar-nav">
                    <li :class="`nav-item ${activeNav == 'query' ? 'active' : null}`">
                        <a class="nav-link" href="{{ route('query') }}"><i class="fas fa-search"></i> 查询</a>
                    </li>
                    <li :class="`nav-item ${activeNav == 'status' ? 'active' : null}`">
                        <a class="nav-link" href="{{ route('status') }}"><i class="fas fa-satellite-dish"></i> 状态</a>
                    </li>
                    @yield('navbar-items')
                </ul>
            </div>
        </nav>
        <div class="horizontal-mobile-message sticky-top p-2 bg-warning border-bottom text-center">请将设备横屏显示以获得最佳体验</div>
        <div class="container">
            @yield('container')
        </div>
        <footer class="footer-outer text-light pt-4 mt-4">
            <div class="container">footer</div>
            <footer class="footer-inner text-center p-3">
                <div class="container">Copyright © 2018 n0099</div>
            </footer>
        </footer>
        <script src="https://www.recaptcha.net/recaptcha/api.js?render={{ $reCAPTCHASiteKey }}"></script>
        <script async src="https://n0099.net/static/browser-update.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/moment@2.24.0/moment.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/echarts@4.1.0/dist/echarts.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/noty@3.1.4/lib/noty.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.js"></script>
        <script async src="https://cdn.jsdelivr.net/npm/lazysizes@4.1.5/lazysizes.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.11/lodash.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue@2.5.21/dist/vue.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue-router@3.0.2/dist/vue-router.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.3.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/gh/morr/jquery.appear@0.4.1/jquery.appear.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
        <script>
            'use strict';
            let $$baseUrl = '{{ $baseUrl }}';
            let $$httpDoamin = '{{ $httpDomain }}';
            let $$baseUrlDir = '{{ $baseUrlDir }}';
            let $$reCAPTCHASiteKey = '{{ $reCAPTCHASiteKey }}';

            //window.noty = new Noty({ timeout: 3000 }); // https://github.com/needim/noty/issues/455
            NProgress.configure({ trickleSpeed: 200 });
            $(document).on('ajaxStart', () => {
                NProgress.start();
            }).on('ajaxStop', () => {
                NProgress.done();
            });
        </script>
        @yield('script-after-container')
    </body>
</html>