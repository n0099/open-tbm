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
        <link href="https://cdn.jsdelivr.net/npm/ant-design-vue@1.3.7/dist/antd.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.6.3/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/noty@3.1.4/lib/noty.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/tippy.js@4.2.0/themes/light-border.css" rel="stylesheet">
        <link href="{{ $baseUrl }}/css/bootstrap-callout.css" rel="stylesheet">
        <style>
            .echarts.loading {
                background: url({{ $baseUrl }}/img/icon-huaji-loading-spinner.gif) no-repeat center;
            }

            .tieba-image-zoom-in {
                position: relative;
            }
            .tieba-image-zoom-in::after {
                position: absolute;
                top: 25px;
                left: 30px;
                content: "\f00e";
                font: 900 2em "Font Awesome 5 Free";
                opacity: 0.4;
                cursor: zoom-in;
            }
            .tieba-image {
                width: 100px;
                height: 100px;
                object-fit: contain;
                cursor: zoom-in;
            }
            .tieba-image-zoom-out {
                cursor: zoom-out;
            }
            .tieba-image-expanded {
                max-width: 80%;
                cursor: zoom-out;
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

            ::-webkit-scrollbar
            {
                width: 10px;
                height: 10px;
                background-color: #f5f5f5;
            }
            ::-webkit-scrollbar-track
            {
                box-shadow: inset 0 0 6px rgba(0,0,0,0.3);
                background-color: #f5f5f5;
            }
            ::-webkit-scrollbar-thumb
            {
                background-color: #f90;
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
                    <li :class="`nav-item ${isActiveNav('query')}`">
                        <a class="nav-link" href="{{ route('query') }}"><i class="fas fa-search"></i> 查询</a>
                    </li>
                    <li :class="`nav-item ${isActiveNav('status')}`">
                        <a class="nav-link" href="{{ route('status') }}"><i class="fas fa-satellite-dish"></i> 状态</a>
                    </li>
                    <li :class="`nav-item ${isActiveNav('stats')}`">
                        <a class="nav-link" href="{{ route('stats') }}"><i class="fas fa-chart-pie"></i> 统计</a>
                    </li>
                    <li :class="`nav-item dropdown ${isActiveNav(['bilibiliVote'])}`">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarTopicDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-paper-plane"></i> 专题
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarTopicDropdown">
                            <a :class="`dropdown-item ${isActiveNav('bilibiliVote')}`" href="{{ route('bilibiliVote') }}">bilibili吧公投</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://n0099.net/donor-list"><i class="fas fa-donate"></i> 捐助</a>
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
            <div class="container">四叶重工QQ群：292311751</div>
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
        <script src="https://cdn.jsdelivr.net/npm/vue@2.5.21/dist/vue{{ App::environment('production') ? '.min' : null }}.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue-router@3.0.2/dist/vue-router.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.3.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/gh/morr/jquery.appear@0.4.1/jquery.appear.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/tippy.js@4.2.0/umd/index.all.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/ant-design-vue@1.3.7/dist/antd.min.js"></script>
        <script>
            'use strict';

            //window.noty = new Noty({ timeout: 3000 }); // https://github.com/needim/noty/issues/455
            NProgress.configure({ trickleSpeed: 200 });
            $(document).ajaxStart(() => {
                NProgress.start();
                $('body').css('cursor', 'progress');
            }).ajaxStop(() => {
                NProgress.done();
                $('body').css('cursor', null);
            }).ajaxError((event, jqXHR) => {
                let errorInfo = '';
                if (jqXHR.responseJSON != null) {
                    let responseErrorInfo = jqXHR.responseJSON;
                    errorInfo = `错误码：${responseErrorInfo.errorCode}<br />${responseErrorInfo.errorInfo}`;
                }
                new Noty({ timeout: 3000, type: 'error', text: `HTTP ${jqXHR.status} ${errorInfo}`}).show();
            });

            const $$tippyInital = () => {
                //tippy('[data-tippy]');
                tippy('[data-tippy-content]');
            };
            tippy.setDefaults({
                animation: 'perspective',
                //followCursor: true,
                interactive: true,
                theme: 'light-border'
            });

            // resize all echarts instance when viewport size changed
            $(window).on('resize', _.throttle(() => {
                $('.echarts').each((k, echartsDOM) => {
                    let echartsInstance = echarts.getInstanceByDom(echartsDOM);
                    if (echartsInstance != null) { // instance might be undefined when echarts haven't been initialed
                        echartsInstance.resize();
                    }
                });
            }, 1000, { leading: false }));

            const $$baseUrl = '{{ $baseUrl }}';
            const $$httpDoamin = '{{ $httpDomain }}';
            const $$baseUrlDir = '{{ $baseUrlDir }}';
            const $$reCAPTCHASiteKey = '{{ $reCAPTCHASiteKey }}';
            const $$reCAPTCHACheck = () => new Promise((resolve, reject) => {
                NProgress.start();
                $('body').css('cursor', 'progress');
                grecaptcha.ready(() => {
                    grecaptcha.execute($$reCAPTCHASiteKey, { action: window.location.pathname })
                        .then((token) => {
                            resolve({ reCAPTCHA: token });
                        }, () => {
                            new Noty({ timeout: 3000, type: 'error', text: 'Google reCAPTCHA 验证未通过 请刷新页面/更换设备/网络环境后重试'}).show();
                        });
                });
            });
            const $$loadForumsList = new Promise((resolve, reject) => {
                $.getJSON(`${$$baseUrl}/api/forumsList`).done((jsonData) => {
                    resolve(_.map(jsonData, (forum) => { // convert every fid to string to ensure fid params value type
                        forum.fid = forum.fid.toString();
                        return forum;
                    }));
                });
            });
            const $$initialNavBar = (activeNav) => {
                window.navBarVue = new Vue({
                    el: '#navbar',
                    data: { $$baseUrl, activeNav },
                    methods: {
                        isActiveNav: function (pageName) {
                            let isActive;
                            if (_.isArray(pageName)) {
                                isActive = pageName.includes(this.$data.activeNav);
                            } else {
                                isActive = this.$data.activeNav === pageName;
                            }
                            return isActive ? 'active' : null;
                        }
                    }
                });
            };
            const $$tiebaImageZoomEventRegister = () => {
                let registerZoomInEvent = (event) => {
                    let tiebaImageDOM = event.currentTarget;
                    $(tiebaImageDOM).removeClass('tieba-image-zoom-in').addClass('tieba-image-zoom-out');
                    $(tiebaImageDOM.children[0]).removeClass('tieba-image').addClass('tieba-image-expanded');
                    $(tiebaImageDOM).off().on('click', registerZoomOutEvent);
                };
                let registerZoomOutEvent = (event) => {
                    let tiebaImageDOM = event.currentTarget;
                    $(tiebaImageDOM).addClass('tieba-image-zoom-in').removeClass('tieba-image-zoom-out');
                    $(tiebaImageDOM.children[0]).addClass('tieba-image').removeClass('tieba-image-expanded');
                    $(tiebaImageDOM).off().on('click', registerZoomInEvent);
                };
                $('.tieba-image-zoom-in').on('click', registerZoomInEvent);
                $('.tieba-image-zoom-out').on('click', registerZoomOutEvent);
            };

            const $$getTiebaPostLink = (tid, pid = null, spid = null) => {
                if (spid != null) {
                    return `https://tieba.baidu.com/p/${tid}?pid=${spid}#${spid}`;
                } else if (pid != null) {
                    return `https://tieba.baidu.com/p/${tid}?pid=${pid}#${pid}`;
                } else {
                    return `https://tieba.baidu.com/p/${tid}`;
                }
            };
            const $$getTBMPostLink = (tid, pid = null, spid = null) => {
                if (spid != null) {
                    return `${$$baseUrl}/query/tid/${tid}`;
                } else if (pid != null) {
                    return `${$$baseUrl}/query/pid/${pid}`;
                } else {
                    return `${$$baseUrl}/query/spid/${spid}`;
                }
            };
            const $$getTiebaUserLink = (username) => {
                return `http://tieba.baidu.com/home/main?un=${username}`;
            };
            const $$getTBMUserLink = (username) => {

            };
        </script>
        @yield('script-after-container')
    </body>
</html>