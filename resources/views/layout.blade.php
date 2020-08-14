<!doctype html>
<html lang="zh-cmn-Hans">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $GATrackingID }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $GATrackingID }}');
        </script>
        @yield('style-module')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.6.3/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/noty@3.1.4/lib/noty.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/tippy.js@4.2.0/themes/light-border.css" rel="stylesheet">
        <style>
            .lazyload, .lazyloading {
                opacity: 0;
                background: #f7f7f7 url({{ asset('img/icon-huaji-loading-spinner.gif') }}) no-repeat center;
            }
            .lazyloaded {
                opacity: 1;
                transition: opacity .3s;
            }
            .loading-icon {
                width: 100px;
                height: 100px;
                background-image: url({{ asset('img/icon-huaji-loading-spinner.gif') }});
                background-size: 100%;
            }

            .grecaptcha-badge {
                visibility: hidden;
            }

            .footer-outer {
                background-color: #2196f3;
            }
            .footer-inner {
                background-color: rgba(0,0,0,.2);
            }

            a {
                color: #1890ff; /* use antd color for clearly bootstrap hover animation */
                text-decoration: none !important; /* override browser underline */
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

            ::-webkit-scrollbar {
                width: 10px;
                height: 10px;
                background-color: #f5f5f5;
            }
            ::-webkit-scrollbar-track {
                box-shadow: inset 0 0 6px rgba(0,0,0,0.3);
                background-color: #f5f5f5;
            }
            ::-webkit-scrollbar-thumb {
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
        @yield('style')
        <title>@yield('title') - 贴吧云监控</title>
    </head>
    <body>
        @yield('body-module')
        <nav class="navbar navbar-expand-lg navbar-light shadow-sm bg-light">
            <a class="navbar-brand" href="{{ $baseUrl }}">贴吧云监控</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbar">
                <ul class="navbar-nav">
                    <li :class="`nav-item dropdown ${isActiveNav(['post', 'user'])}`">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarQueryDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-search"></i> 查询</a>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarQueryDropdown">
                            <a :class="`dropdown-item ${isActiveNav('post')}`" href="{{ route('post') }}"><i class="far fa-comment-dots"></i> 贴子</a>
                            <a :class="`dropdown-item ${isActiveNav('user')}`" href="{{ route('user') }}"><i class="fas fa-users"></i> 用户</a>
                        </div>
                    </li>
                    <li :class="`nav-item ${isActiveNav('stats')}`">
                        <a class="nav-link" href="{{ route('stats') }}"><i class="fas fa-chart-pie"></i> 统计</a>
                    </li>
                    <li :class="`nav-item ${isActiveNav('status')}`">
                        <a class="nav-link" href="{{ route('status') }}"><i class="fas fa-satellite-dish"></i> 状态</a>
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
                </ul>
            </div>
        </nav>
        <div class="horizontal-mobile-message sticky-top p-2 bg-warning border-bottom text-center">请将设备横屏显示以获得最佳体验</div>
        <div class="container">
            @yield('container')
        </div>
        @yield('body')
        <footer class="footer-outer text-light pt-4 mt-4">
            <div class="text-center container">
                <p>四叶重工QQ群：292311751</p>
                <p>
                    Google <a class="text-white" href="https://www.google.com/analytics/terms/cn.html" target="_blank">Analytics 服务条款</a> |
                    <a class="text-white" href="https://policies.google.com/terms" target="_blank">reCAPTCHA 服务条款</a> |
                    <a class="text-white" href="https://policies.google.com/privacy" target="_blank">隐私条款</a>
                </p>
            </div>
            <footer class="footer-inner text-center p-3">
                <div class="container">© 2018 ~ 2020 n0099</div>
            </footer>
        </footer>
        <script src="https://www.recaptcha.net/recaptcha/api.js?render={{ $reCAPTCHASiteKey }}"></script>
        <script async src="https://n0099.net/static/browser-update.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/moment@2.24.0/moment.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/moment@2.24.0/locale/zh-cn.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/noty@3.1.4/lib/noty.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.js"></script>
        <script async src="https://cdn.jsdelivr.net/npm/lazysizes@4.1.5/lazysizes.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.11/lodash.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue@2.6.10/dist/vue{{ App::environment('production') ? '.min' : null }}.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue-router@3.0.2/dist/vue-router.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.3.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/tippy.js@4.2.0/umd/index.all.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
        @yield('script-module')
        <script>
            'use strict';

            moment.locale('zh-cn');
            //window.noty = new Noty({ timeout: 3000 }); // https://github.com/needim/noty/issues/455
            NProgress.configure({ trickleSpeed: 200 });
            const $$changePageLoading = (isLoading) => {
                if (isLoading) {
                    NProgress.start();
                    $('body').css('cursor', 'progress');
                } else {
                    NProgress.done();
                    $('body').css('cursor', '');
                }
            };
            $(document)
                .ajaxStart(() => $$changePageLoading(true))
                .ajaxStop(() => $$changePageLoading(false))
                .ajaxError((event, jqXHR) => {
                    let errorInfo = '';
                    if (jqXHR.responseJSON !== undefined) {
                        let error = jqXHR.responseJSON;
                        if (_.isObject(error.errorInfo)) { // response when laravel failed validate
                            errorInfo = `错误码：${error.errorCode}<br />${_.map(error.errorInfo, (info, paramName) => `参数 ${paramName}：${info.join('<br />')}`).join('<br />')}`;
                        } else {
                            errorInfo = `错误码：${error.errorCode}<br />${error.errorInfo}`;
                        }
                    }
                    new Noty({ timeout: 3000, type: 'error', text: `HTTP ${jqXHR.status} ${errorInfo}`}).show();
                });

            const $$registerTippy = (scopedRootDom = 'body', unregister = false) => {
                if (unregister) {
                    _.each($(scopedRootDom).find('[data-tippy-content]'), (dom) => {
                        dom._tippy.destroy();
                    });
                } else {
                    tippy($(scopedRootDom).find('[data-tippy-content]').get());
                }
            };
            tippy.setDefaults({
                animation: 'perspective',
                interactive: true,
                theme: 'light-border'
            });

            const $$baseUrl = '{{ $baseUrl }}';
            const $$baseUrlDir = $$baseUrl.substr($$baseUrl.indexOf('/', $$baseUrl.indexOf('://') + 3));
            const $$reCAPTCHASiteKey = '{{ $reCAPTCHASiteKey }}';
            const $$reCAPTCHACheck = () => new Promise((resolve, reject) => {
                @if(\App::environment('production'))
                NProgress.start();
                $('body').css('cursor', 'progress');
                grecaptcha.ready(() => {
                    grecaptcha.execute($$reCAPTCHASiteKey)
                        .then((token) => {
                            resolve(token);
                            $$changePageLoading(false);
                        }, () => {
                            reject();
                            $$changePageLoading(false);
                            new Noty({ timeout: 3000, type: 'error', text: 'Google reCAPTCHA 验证未通过 请刷新页面/更换设备/网络环境后重试'}).show();
                        });
                });
                @else
                resolve({ reCAPTCHA: null});
                @endif
            });
            const $$initialNavBar = (activeNav) => {
                window.navBarVue = new Vue({
                    el: '#navbar',
                    data: { $$baseUrl, activeNav },
                    methods: {
                        isActiveNav (pageName) {
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

            const $$loadForumList = () => new Promise((resolve) => $.getJSON(`${$$baseUrl}/api/forumsList`).done((ajaxData) => resolve(ajaxData)));

            const $$getTiebaPostLink = (tid, pid = null, spid = null) => {
                if (spid !== null) {
                    return `https://tieba.baidu.com/p/${tid}?pid=${spid}#${spid}`;
                } else if (pid !== null) {
                    return `https://tieba.baidu.com/p/${tid}?pid=${pid}#${pid}`;
                } else {
                    return `https://tieba.baidu.com/p/${tid}`;
                }
            };
            const $$getTBMPostLink = (tid, pid = null, spid = null) => {
                if (spid !== null) {
                    return `${$$baseUrl}/post/tid/${tid}`;
                } else if (pid !== null) {
                    return `${$$baseUrl}/post/pid/${pid}`;
                } else {
                    return `${$$baseUrl}/post/spid/${spid}`;
                }
            };
            const $$getTiebaUserLink = (username) => `http://tieba.baidu.com/home/main?un=${username}`;
            const $$getTBMUserLink = (username) => `${$$baseUrl}/user/n/${username}`;
            const $$getTiebaUserAvatarUrl = (avatarUrl) => `https://himg.bdimg.com/sys/portrait/item/${avatarUrl}.jpg`;
        </script>
        @yield('script')
    </body>
</html>
