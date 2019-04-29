@php($baseUrl = env('APP_URL'))
@php($httpDomain = implode('/', array_slice(explode('/', $baseUrl), 0, 3)))
@php($baseUrlDir = substr($baseUrl, strlen($httpDomain)))
@php($reCAPTCHASiteKey = env('reCAPTCHA_SITE_KEY'))
@php($GATrackingID = env('GA_TRACKING_ID'))
<!doctype html>
<html lang="zh-cmn-Hans">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $GATrackingID }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $GATrackingID }}');
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
                content: "\f00e"; /* fa-search-plus */
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

            .grecaptcha-badge {
                visibility: hidden;
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
                    @yield('navbar-items')
                </ul>
            </div>
        </nav>
        <div class="horizontal-mobile-message sticky-top p-2 bg-warning border-bottom text-center">请将设备横屏显示以获得最佳体验</div>
        <div class="container">
            @yield('container')
        </div>
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
                <div class="container">© 2018 ~ 2019 n0099</div>
            </footer>
        </footer>
        <script src="https://www.recaptcha.net/recaptcha/api.js?render={{ $reCAPTCHASiteKey }}"></script>
        <script async src="https://n0099.net/static/browser-update.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/moment@2.24.0/moment.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/moment@2.24.0/locale/zh-cn.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/echarts@4.1.0/dist/echarts.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/noty@3.1.4/lib/noty.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.js"></script>
        <script async src="https://cdn.jsdelivr.net/npm/lazysizes@4.1.5/lazysizes.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.11/lodash.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue@2.6.10/dist/vue{{ App::environment('production') ? '.min' : null }}.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue-router@3.0.2/dist/vue-router.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vue-observe-visibility@0.4.3/dist/vue-observe-visibility.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/intersection-observer@0.5.1/intersection-observer.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.3.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/gh/morr/jquery.appear@0.4.1/jquery.appear.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/tippy.js@4.2.0/umd/index.all.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/ant-design-vue@1.3.7/dist/antd.min.js"></script>
        <template id="scroll-list-template">
            <div :id="`scroll-list-${scrollListID}`"
                 v-observe-visibility="{ callback: listVisibilityChanged, throttle: 100, intersection: { threshold: 0.01 } }">
                <component :is="itemOuterTagsName" v-for="(item, itemIndex) in items" :key="itemIndex"
                           v-eval-dynamic-dimensions="shouldDisplay(itemIndex)"
                           v-observe-visibility="{ callback: itemVisibilityChanged, throttle: 100, intersection: { threshold: 0.01 } }"
                           v-initial-item-dimensions="itemInitialDimensions"
                           v-bind="evalItemAttrs('outer', items, item, itemIndex)"
                           :data-item-index="itemIndex">
                    <transition v-if="itemTransitionName != null" :name="itemTransitionName">
                        <component :is="itemInnerTagsName" v-if="shouldDisplay(itemIndex)"
                                   v-bind="evalItemAttrs('inner', items, item, itemIndex)">
                            <slot :item="item"></slot>
                        </component>
                    </transition>
                    <template v-else>
                        <component :is="itemInnerTagsName" v-if="shouldDisplay(itemIndex)"
                                   v-bind="evalItemAttrs('inner', items, item, itemIndex)">
                            <slot :item="item"></slot>
                        </component>
                    </template>
                </component>
            </div>
        </template>
        <script>
            'use strict';

            const scrollListComponent = Vue.component('scroll-list', {
                template: '#scroll-list-template',
                directives: {
                    'eval-dynamic-dimensions': {
                        update: function (el, binding, vnode) {
                            let vue = vnode.context;
                            if (vue.$props.itemDynamicDimensions === true) {
                                let isDisplaying =  binding.value;
                                if (isDisplaying !== binding.oldValue) { // is value changed
                                    if (isDisplaying) { // reset item dom's dimensions to allow user changing dom height and width
                                        el.style.height = null;
                                        el.style.width = null;
                                    } else { // remain origin item dom's height and width to ensure viewport dimensions not change (height sink)
                                        let itemIndex = parseInt(el.getAttribute('data-item-index')); // fetch dimensions from previous cache
                                        let cachedItemDimensions = vue.$data.itemDOMDimensionsCache[itemIndex];
                                        el.style.height = cachedItemDimensions == null ? null : `${cachedItemDimensions.height}px`;
                                        el.style.width = cachedItemDimensions == null ? null : `${cachedItemDimensions.width}px`;
                                    }
                                }
                            }
                        }
                    },
                    'initial-item-dimensions': {
                        bind: function (el, binding) {
                            // set initial items height and width to prevent initialed hiding item stacked at one pixel
                            el.style.height = binding.value.height;
                            el.style.width = binding.value.width;
                        }
                    }
                },
                props: {
                    items: {
                        type: Array,
                        required: true
                    },
                    itemDynamicDimensions: {
                        type: Boolean,
                        required: true
                    },
                    itemInitialDimensions: {
                        type: Object,
                        required: true
                    },
                    itemsShowingNum: {
                        type: Number,
                        required: true
                    },
                    itemTransitionName: String,
                    itemOuterAttrs: Object,
                    itemInnerAttrs: Object,
                    itemOuterTags: String,
                    itemInnerTags: String,
                    itemObserveEvent: String,
                    itemPlaceholderClass: String
                },
                data: function () {
                    return {
                        displayingItemsID: [],
                        scrollListID: '',
                        itemDOMDimensionsCache: [],
                        itemEvaledAttrsValue: { outer: {}, inner: {} },
                        itemOuterTagsName: this.$props.itemOuterTags || 'div',
                        itemInnerTagsName: this.$props.itemOuterTags || 'div'
                    };
                },
                created: function () {
                    // initial props and data's value with default value
                    let initialDimensions = this.$props.itemInitialDimensions;
                    initialDimensions.height = initialDimensions.height || '';
                    initialDimensions.width = initialDimensions.width || '';
                    this.$data.scrollListID = Math.random().toString(36).substring(5);
                },
                mounted: function () {
                    this.$data.displayingItemsID = this.range(0, this.$props.itemsShowingNum); // initially showing first $itemsShowingNum items
                },
                methods: {
                    evalItemAttrs: function (renderPosition, items, item, itemIndex) {
                        let cachedEvalValue = this.$data.itemEvaledAttrsValue[renderPosition][itemIndex];
                        if (cachedEvalValue == null) {
                            let evaledValue = {};
                            let itemsAttrs = renderPosition === 'outer'
                                ? this.$props.itemOuterAttrs
                                : (renderPosition === 'inner'
                                    ? this.$props.itemInnerAttrs
                                    : (() => { throw 'items attr render position not valid'; })());
                            if (itemsAttrs != null) {
                                Object.keys(itemsAttrs).forEach((attrName) => {
                                    let itemAttrs = itemsAttrs[attrName];
                                    if (itemAttrs.type === 'eval') {
                                        let evalValue = new Function('items', 'item', 'itemIndex', `return ${itemAttrs.value}`);
                                        evaledValue[attrName] = evalValue(items, item, itemIndex).toString();
                                    } else if (itemAttrs.type === 'string') {
                                        evaledValue[attrName] = itemAttrs.value;
                                    } else {
                                        throw 'item attrs render type not valid';
                                    }
                                });
                                this.$data.itemEvaledAttrsValue[renderPosition][itemIndex] = evaledValue; // cache evaluated attrs value
                                return evaledValue;
                            } else {
                                return {};
                            }
                        } else {
                            // add itemPlaceholderClass to class attr value when hiding item
                            if (this.$props.itemPlaceholderClass != null
                                && renderPosition === 'outer'
                                && ! this.shouldDisplay(itemIndex)) {
                                cachedEvalValue = Object.assign({}, cachedEvalValue); // shadow copy to prevent mutate cache
                                if (cachedEvalValue.class == null) {
                                    cachedEvalValue.class = this.$props.itemPlaceholderClass;
                                } else {
                                    cachedEvalValue.class += ` ${this.$props.itemPlaceholderClass}`;
                                }
                            }
                            return cachedEvalValue;
                        }
                    },
                    shouldDisplay: function (itemIndex) {
                        let displayingItemsID = this.$data.displayingItemsID;
                        return itemIndex >= displayingItemsID[0] && itemIndex <= displayingItemsID[displayingItemsID.length - 1]
                    },
                    listVisibilityChanged: function (isVisible, observer) {
                        if (! isVisible) { // hide all items when viewport is leaving whole scroll list
                            this.$data.displayingItemsID = [];
                        }
                    },
                    itemVisibilityChanged: function (isVisible, observer) {
                        let itemDOM = observer.target;
                        let itemIndex = parseInt(itemDOM.getAttribute('data-item-index'));
                        if (isVisible) {
                            // moving displaying items index
                            this.$data.displayingItemsID = this.getDisplayIndexRange(0, this.$props.items.length, itemIndex, this.$props.itemsShowingNum);
                        } else {
                            // cache current hiding item dom's height and width px before hided
                            this.$data.itemDOMDimensionsCache[itemIndex] = { height: itemDOM.offsetHeight, width: itemDOM.offsetWidth };
                        }
                        // call user defined parent component event
                        let parentCompentEventName = this.$props.itemObserveEvent;
                        if (parentCompentEventName != null) {
                            this.$emit(parentCompentEventName, isVisible, observer);
                        }
                    },
                    range: function (start, end) { // [start, end)
                        return new Array(end - start).fill().map((d, i) => i + start);
                    },
                    getDisplayIndexRange: function (rangeLowerBound, rangeUpperBound, median, rangeSize) {
                        /* output example
                         * (0, 20, 0, 5) => [0, 1, 2, 3, 4]
                         * (0, 20, 1, 4) => [0, 1, 2, 3]
                         * (1, 20, 10, 4) => [9, 10, 11, 12]
                         * (1, 20, 10, 5) => [8, 9, 10, 11, 12]
                         * (1, 20, 19, 5) => [16, 17, 18, 19, 20]
                         */
                        let distanceFromMedianToRangeSize = Math.floor(rangeSize / 2); // the distance from median value to output array lower/upper bound
                        let isStartFromLowerBound = median - distanceFromMedianToRangeSize < rangeLowerBound;
                        let isEndAtUpperBound = median + distanceFromMedianToRangeSize > rangeUpperBound;
                        let out = [];
                        if (isStartFromLowerBound) {
                            out = this.range(rangeLowerBound, rangeSize); // start from rangeLowerBound will restrict output range size won't <rangeLowerBound
                        } else if (isEndAtUpperBound) {
                            out = this.range(rangeUpperBound - rangeSize + 1, rangeUpperBound + 1); // start from rangeUpperBound - rangeSize will restrict output range size won't >rangeUpperBound
                        } else {
                            out = this.range(median - distanceFromMedianToRangeSize, median + distanceFromMedianToRangeSize + 1); // normally median range
                            if (rangeSize % 2 === 0) {
                                out.shift(); // remove first lowest value to align size when required output range size is even number
                            }
                        }
                        return out;
                    }
                }
            });

            moment.locale('zh-cn');

            //window.noty = new Noty({ timeout: 3000 }); // https://github.com/needim/noty/issues/455
            NProgress.configure({ trickleSpeed: 200 });
            $(document).ajaxStart(() => {
                NProgress.start();
                $('body').css('cursor', 'progress');
            }).ajaxStop(() => {
                NProgress.done();
                $('body').css('cursor', '');
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
                    grecaptcha.execute($$reCAPTCHASiteKey)
                        .then((token) => {
                            resolve({ reCAPTCHA: token });
                        }, () => {
                            new Noty({ timeout: 3000, type: 'error', text: 'Google reCAPTCHA 验证未通过 请刷新页面/更换设备/网络环境后重试'}).show();
                            NProgress.done();
                            $('body').css('cursor', '');
                        });
                });
            });
            const $$loadForumsList = () => new Promise((resolve, reject) => {
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
                    return `${$$baseUrl}/post/tid/${tid}`;
                } else if (pid != null) {
                    return `${$$baseUrl}/post/pid/${pid}`;
                } else {
                    return `${$$baseUrl}/post/spid/${spid}`;
                }
            };
            const $$getTiebaUserLink = (username) => {
                return `http://tieba.baidu.com/home/main?un=${username}`;
            };
            const $$getTBMUserLink = (username) => {

            };
            const $$getTiebaUserAvatarUrl = (avatarUrl) => {
                return `https://himg.bdimg.com/sys/portrait/item/${avatarUrl}.jpg`;
            };
        </script>
        @yield('script-after-container')
    </body>
</html>