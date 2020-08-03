@extends('layout')
@include('module.bootstrapCallout')
@include('module.tiebaPostContentElement')
@include('module.vue.scrollList')
@include('module.vue.tiebaSelectUser')
@include('module.vue.antd')

@section('title', '贴子查询')

@section('style')
    <style>/* <error-placeholder> */
        .error-code {
            font-size: 4em;
        }
    </style>
    <style>/* <select-param> */
        .select-param.is-invalid {
            z-index: 1; /* let border overlaps other selects */
        }
        .select-param-first-row {
            border-top-left-radius: 0.25rem !important;
        }
        .select-param-last-row {
            border-bottom-left-radius: 0.25rem !important;
        }
    </style>
    <style>/* <query-form> */
        .query-param-row {
            margin-top: -1px;
        }
        .param-control-first-row {
            border-bottom-right-radius: 0;
        }
        .param-control-middle-row {
            border-bottom-right-radius: 0;
            border-top-right-radius: 0;
        }
        .param-control-last-row {
            border-top-right-radius: 0;
        }
        .param-intput-group-text {
            background-color: unset;
        }

        .a-datetime-range {
            margin-left: -1px;
        }
        .ant-calendar-picker-input {
            height: 38px;
            border-bottom-left-radius: 0;
            border-top-left-radius: 0;
        }

        .add-param-button { /* fa-plus is wider than fa-times 3px */
            padding-left: 10px;
            padding-right: 11px;
        }
    </style>
    <style>/* <post-render-table> */
        .ant-table {
            width: fit-content;
        }

        .render-table-thread .ant-table { /* todo: should only select first appeared child */
            width: auto;
            border: 1px solid #e8e8e8;
            border-radius: 4px 4px 0 0;
        }

        .ant-table td, .ant-table th {
            white-space: nowrap;
            font-family: Consolas, Courier New, monospace;
        }

        .ant-table-expand-icon-th, .ant-table-row-expand-icon-cell {
            width: 1px; /* any value other than 0px */
            min-width: unset;
            padding-left: 5px !important;
            padding-right: 5px !important;
        }
    </style>
    <style>/* <post-render-raw> */
        .render-raw {
            resize: both;
            height: 50rem;
            font-family: Consolas, Courier New, monospace;
        }
    </style>
    <style>/* <post-page-previous-button> */
        .previous-page-button {
            position: relative;
            top: -1.5rem;
            margin-bottom: -1rem;
            padding-bottom: 4px;
            padding-top: 4px;
        }
    </style>
    <style>/* common */
        .loading-list-placeholder .post-item-placeholder {
            height: 480px;
        }
        .post-item-placeholder {
            background-image: url({{ asset('img/tombstone-post-list.svg') }});
            background-size: 100%;
        }
    </style>
@endsection

@section('container')
    @verbatim
        <template id="input-text-match-param-template">
            <div class="input-group-append">
                <div :class="classes" class="input-group-text">
                    <div class="custom-radio custom-control custom-control-inline">
                        <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'implicit'" :id="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`"
                               :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="implicit" type="radio" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`" class="custom-control-label">模糊</label>
                    </div>
                    <div class="custom-radio custom-control custom-control-inline">
                        <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'explicit'" :id="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`"
                               :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="explicit" type="radio" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`" class="custom-control-label">精确</label>
                    </div>
                    <div class="custom-checkbox custom-control custom-control-inline">
                        <input @input="modelEvent('spaceSplit', $event.target.checked)" :checked="param.subParam.spaceSplit" :id="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`"
                               :disabled="param.subParam.matchBy === 'regex'" type="checkbox" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`" class="custom-control-label">空格分隔</label>
                    </div>
                    <div class="custom-radio custom-control">
                        <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'regex'" :id="`param${_.upperFirst(param.name)}Regex-${paramIndex}`"
                               :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="regex" type="radio" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}Regex-${paramIndex}`" class="custom-control-label">正则</label>
                    </div>
                </div>
            </div>
        </template>
        <template id="input-numeric-param-template">
            <input v-if="param.subParam.range === 'IN'" @input="modelEvent($event.target.value)" :value="param.value"
                   :class="classes" :placeholder="placeholders.IN" :aria-label="param.name"
                   type="text" class="col form-control" required pattern="\d+(,\d+)+" />
            <input v-else-if="param.subParam.range === 'BETWEEN'" @input="modelEvent($event.target.value)" :value="param.value"
                   :class="classes" :placeholder="placeholders.BETWEEN" :aria-label="param.name"
                   type="text" class="col-3 form-control" required pattern="\d+,\d+" />
            <input v-else @input="modelEvent($event.target.value)" :value="param.value"
                   :class="classes" :placeholder="placeholders.number" :aria-label="param.name"
                   type="number" class="col-2 form-control" required />
        </template>
        <template id="select-range-template">
            <select @input="$emit('input', $event.target.value)" :value="value" class="col-1 custom-select form-control">
                <option>&lt;</option>
                <option>=</option>
                <option>&gt;</option>
                <option>IN</option>
                <option>BETWEEN</option>
            </select>
        </template>
        <template id="select-param-template">
            <select @change="$emit('param-change', $event)" class="col-2 custom-select form-control">
                <option selected value="add" disabled>New...</option>
                <optgroup v-for="(group, groupName) in paramsGroup" :label="groupName">
                    <option v-for="(paramDescription, paramName) in group" :selected="currentParam === paramName" :value="paramName">{{ paramDescription }}</option>
                </optgroup>
            </select>
        </template>
        <template id="query-form-template">
            <form @submit.prevent="submit()" class="mt-3">
                <div class="form-group form-row">
                    <label class="col-1 col-form-label" for="paramFid">贴吧</label>
                    <div class="col-3 input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                        </div>
                        <select v-model.number="uniqueParams.fid.value" :class="{ 'is-invalid': isFidInvalid }" id="paramFid" class="custom-select form-control">
                            <option value="NULL">未指定</option>
                            <option v-for="forum in forumList" :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                        </select>
                    </div>
                    <label class="col-1 col-form-label text-center">贴子类型</label>
                    <div class="input-group my-auto col">
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypesThread" type="checkbox" value="thread" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypesThread">主题贴</label>
                        </div>
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypesReply" type="checkbox" value="reply" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypesReply">回复贴</label>
                        </div>
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypesSubReply" type="checkbox" value="subReply" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypesSubReply">楼中楼</label>
                        </div>
                    </div>
                </div>
                <div class="form-group form-row">
                    <label class="col-1 col-form-label" for="paramOrder">排序方式</label>
                    <div id="paramOrder" class="col-8 input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-sort-amount-down"></i></span>
                        </div>
                        <select v-model="uniqueParams.orderBy.value" :class="{ 'is-invalid': isOrderByInvalid }" class="col custom-select form-control">
                            <option value="default">默认（按贴索引查询按发贴时间正序，按吧索引/搜索查询倒序）</option>
                            <option value="postTime">发贴时间</option>
                            <optgroup label="贴子ID">
                                <option value="tid">主题贴tid</option>
                                <option value="pid">回复贴pid</option>
                                <option value="spid">楼中楼spid</option>
                            </optgroup>
                        </select>
                        <select v-show="uniqueParams.orderBy.value !== 'default'" v-model="uniqueParams.orderBy.subParam.direction" class="col-6 custom-select form-control">
                            <option value="default">默认（按贴索引查询正序，按吧索引/搜索查询倒序）</option>
                            <option value="ASC">正序（从小到大，旧到新）</option>
                            <option value="DESC">倒序（从大到小，新到旧）</option>
                        </select>
                    </div>
                </div>
                <div class="query-param-row form-row" v-for="(param, paramIndex) in params">
                    <div class="input-group">
                        <button @click="deleteParam(paramIndex)" class="btn btn-link" type="button"><i class="fas fa-times"></i></button>
                        <select-param @param-change="changeParam(paramIndex, $event.target.value)" :current-param="param.name"
                                      :class="{
                                        'is-invalid': invalidParamsIndex.includes(paramIndex),
                                        'select-param-first-row': paramIndex === 0,
                                        'select-param-last-row': paramIndex === params.length - 1,
                                        'select-param': true
                                      }"></select-param>
                        <template v-if="_.includes(['tid', 'pid', 'spid'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDOMClass(paramIndex, params)"
                                                 :placeholders="{
                                                    IN: param.name === 'tid' ? '5000000000,5000000001,5000000002,...' : '15000000000,15000000001,15000000002,...',
                                                    BETWEEN: param.name === 'tid' ? '5000000000,6000000000' : '15000000000,16000000000',
                                                    number: param.name === 'tid' ? 5000000000 : 15000000000
                                                 }"></input-numeric-param>
                        </template>
                        <template v-if="_.includes(['postTime', 'latestReplyTime'], param.name)">
                            <a-range-picker v-model="param.subParam.range" :locale="antd.locales.zh_CN.DatePicker" :show-time="true"
                                            format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DDTHH:mm" size="large" class="a-datetime-range"></a-range-picker>
                        </template>
                        <template v-if="_.includes(['threadTitle', 'postContent', 'authorName', 'authorDisplayName', 'latestReplierName', 'latestReplierDisplayName'], param.name)">
                            <input v-model="param.value" :placeholder="`${param.subParam.matchBy === 'implicit' ? '模糊' : (param.subParam.matchBy === 'explicit' ? '精确' : '正则')}匹配 空格${param.subParam.spaceSplit ? '不' : ''}分割关键词`" type="text" class="form-control" required>
                            <input-text-match-param v-model="params[paramIndex]" :param-index="paramIndex" :classes="paramRowLastDOMClass(paramIndex, params)"></input-text-match-param>
                        </template>
                        <template v-if="_.includes(['threadViewNum', 'threadShareNum', 'threadReplyNum', 'replySubReplyNum'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDOMClass(paramIndex, params)"
                                                 :placeholders="{
                                                    IN: '100,101,102,...',
                                                    BETWEEN: '100,200',
                                                    number: 100
                                                 }"></input-numeric-param>
                        </template>
                        <template v-if="param.name === 'threadProperties'">
                            <div class="input-group-append">
                                <div class="param-intput-group-text input-group-text">
                                    <div class="custom-checkbox custom-control">
                                        <input v-model="param.value" :id="`paramThreadPropertiesGood-${paramIndex}`" type="checkbox" value="good" class="custom-control-input">
                                        <label :for="`paramThreadPropertiesGood-${paramIndex}`" class="text-danger font-weight-normal custom-control-label">精品</label>
                                    </div>
                                </div>
                            </div>
                            <div class="input-group-append">
                                <div :class="paramRowLastDOMClass(paramIndex, params)" class="param-intput-group-text input-group-text">
                                    <div class="custom-checkbox custom-control">
                                        <input v-model="param.value" :id="`paramThreadPropertiesSticky-${paramIndex}`" type="checkbox" value="sticky" class="custom-control-input">
                                        <label :for="`paramThreadPropertiesSticky-${paramIndex}`" class="text-primary font-weight-normal custom-control-label">置顶</label>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-if="_.includes(['authorUid', 'latestReplierUid'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDOMClass(paramIndex, params)"
                                                 :placeholders="{
                                                    IN: '4000000000,4000000001,4000000002,...',
                                                    BETWEEN: '4000000000,5000000000',
                                                    number: 4000000000
                                                 }"></input-numeric-param>
                        </template>
                        <template v-if="param.name === 'authorManagerType'">
                            <select value="NULL" v-model="param.value" class="col-2 form-control">
                                <option value="NULL">吧友</option>
                                <option value="manager">吧主</option>
                                <option value="assist">小吧主</option>
                                <option value="voiceadmin">语音小编</option>
                            </select>
                        </template>
                        <template v-if="_.includes(['authorGender', 'latestReplierGender'], param.name)">
                            <select v-model="param.value" class="col-2 form-control">
                                <option selected value="0">未设置（显示为男）</option>
                                <option value="1">男 ♂</option>
                                <option value="2">女 ♀</option>
                            </select>
                        </template>
                        <template v-if="param.name === 'authorExpGrade'">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDOMClass(paramIndex, params)"
                                                 :placeholders="{
                                                    IN: '9,10,11,...',
                                                    BETWEEN: '9,18',
                                                    number: 18
                                                 }"></input-numeric-param>
                        </template>
                    </div>
                </div>
                <div class="mt-1 form-group form-row">
                    <button class="add-param-button disabled btn btn-link" type="button"><i class="fas fa-plus"></i></button>
                    <select-param @param-change="addParam($event)"></select-param>
                </div>
                <div class="form-group form-row">
                    <button :disabled="isRequesting" type="submit" class="btn btn-primary">
                        查询 <span v-show="isRequesting" class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
                    </button>
                    <button class="ml-2 disabled btn btn-text" type="button">{{ currentQueryType() === 'fid' ? '按吧索引查询' : (currentQueryType() === 'postID' ? '按贴索引查询' : (currentQueryType() === 'search' ? '搜索查询' : '空查询')) }}</button>
                </div>
            </form>
        </template>
        <template id="post-render-list-template">

        </template>
        <template id="post-render-table-template">
            <div class="container-flow mb-2">
                <a-table :columns="threadColumns" :data-source="threads" :default-expand-all-rows="true" :expand-row-by-click="true" :pagination="false" :scroll="{ x: true }" size="middle" class="render-table-thread">
                    <template slot="expandedRowRender" slot-scope="record">
                        <p v-html="record.title"></p>
                        <a-table v-if="threadsReply[record.tid] !== undefined" :columns="replyColumns" :data-source="threadsReply[record.tid]" :default-expand-all-rows="true" :expand-row-by-click="true" :pagination="false" size="middle">
                            <template slot="expandedRowRender" slot-scope="record">
                                <div :is="repliesSubReply[record.pid] === undefined ? 'span' : 'p'" v-html="record.content"></div>
                                <a-table v-if="repliesSubReply[record.pid] !== undefined" :columns="subReplyColumns" :data-source="repliesSubReply[record.pid]" :default-expand-all-rows="true" :expand-row-by-click="true" :pagination="false" size="middle">
                                    <template slot="expandedRowRender" slot-scope="record">
                                        <span v-html="record.content"></span>
                                    </template>
                                </a-table>
                            </template>
                        </a-table>
                    </template>
                </a-table>
            </div>
        </template>
        <template id="post-render-raw-template">
            <textarea :value="JSON.stringify(posts)" class="render-raw border col"></textarea>
        </template>
        <template id="post-page-previous-button-template">
            <div v-scroll-to-page="{ currentPage: pageInfo.currentPage, scrollToPage: scrollToPage.value }" class="mt-3 p-2 row align-items-center">
                <div class="col"><hr /></div>
                <div class="w-auto">
                    <div class="p-2 badge badge-light">
                        <button v-if="pageInfo.currentPage > 1" @click="loadPage(pageInfo.currentPage - 1)" class="previous-page-button btn btn-primary" type="button">上一页</button>
                        <p class="h4">第 {{ pageInfo.currentPage }} 页</p>
                        <span class="small">第 {{ pageInfo.firstItem }}~{{ pageInfo.firstItem + pageInfo.currentItems - 1 }} 条</span>
                    </div>
                </div>
                <div class="col"><hr /></div>
            </div>
        </template>
        <template id="post-page-next-button-template">
            <div class="p-4">
                <div class="row align-items-center">
                    <div class="col"><hr /></div>
                    <div class="w-auto">
                        <button @click="loadPage(currentPage + 1)" class="btn btn-secondary" type="button">
                            <span class="h4">下一页</span>
                        </button>
                    </div>
                    <div class="col"><hr /></div>
                </div>
            </div>
        </template>
        <template id="posts-query-template">
            <div>
                <query-form @query="query($event)" :forum-list="forumList" ref="queryForm"></query-form>
                <p>当前页数：{{ currentRoutesPage }}</p>
                <a-tabs v-model="renderType">
                    <a-tab-pane key="list" tab="列表视图">
                        <template v-if="renderType === 'list' && queryError === null">
                            <template v-for="(posts, pageIndex) in postPages">
                                <post-page-previous-button @load-page="loadPage($event)" :page-info="posts.pages"></post-page-previous-button>
                                <post-render-list :posts="posts"></post-render-list>
                                <post-page-next-button v-if="! $refs.queryForm.$data.isRequesting && pageIndex === postPages.length - 1" @load-page="loadPage($event)" :current-page="posts.pages.currentPage"></post-page-next-button>
                            </template>
                        </template>
                    </a-tab-pane>
                    <a-tab-pane key="table" tab="表格视图">
                        <template v-if="renderType === 'table' && queryError === null">
                            <template v-for="(posts, pageIndex) in postPages">
                                <post-page-previous-button @load-page="loadPage($event)" :page-info="posts.pages"></post-page-previous-button>
                                <post-render-table :posts="posts"></post-render-table>
                                <post-page-next-button v-if="! $refs.queryForm.$data.isRequesting && pageIndex === postPages.length - 1" @load-page="loadPage($event)" :current-page="posts.pages.currentPage"></post-page-next-button>
                            </template>
                        </template>
                    </a-tab-pane>
                    <a-tab-pane key="raw" tab="RAW">
                        <template v-if="renderType === 'raw' && queryError === null">
                            <template v-for="(posts, pageIndex) in postPages">
                                <post-page-previous-button @load-page="loadPage($event)" :page-info="posts.pages"></post-page-previous-button>
                                <post-render-raw :posts="posts"></post-render-raw>
                                <post-page-next-button v-if="! $refs.queryForm.$data.isRequesting && pageIndex === postPages.length - 1" @load-page="loadPage($event)" :current-page="posts.pages.currentPage"></post-page-next-button>
                            </template>
                        </template>
                    </a-tab-pane>
                </a-tabs>
                <error-placeholder v-if="queryError !== null" :error="queryError"></error-placeholder>
                <loading-list-placeholder v-show="($refs.queryForm || { $data: { isRequesting: false } }).$data.isRequesting"></loading-list-placeholder>
            </div>
        </template>
        <template id="error-placeholder-template">
            <div class="text-center">
                <hr />
                <p class="error-code">{{ error.code }}</p>
                <p v-html="error.info"></p>
            </div>
        </template>
        <div id="posts-query">
            <router-view></router-view>
            <!-- use div instead of template to display dom before vue loaded, v-show=false to hide dom after vue finish load -->
            <div v-show="false" id="loading-list-placeholder-template">
                <div class="loading-list-placeholder row align-items-center">
                    <div class="col"><hr /></div>
                    <div class="w-auto">
                        <div class="loading-icon mx-auto"></div>
                    </div>
                    <div class="col"><hr /></div>
                    <div class="w-100"></div>
                    <div class="col">
                        <div class="post-item-placeholder"></div>
                    </div>
                </div>
            </div>
        </div>
    @endverbatim
@endsection

@section('script')
    @verbatim
        <script>
            'use strict';
            $$initialNavBar('postMulti');

            window.sharedDataBindings = {
                scrollToPage: { value: 0 }
            };

            const postPagePreviousButtonComponent = Vue.component('post-page-previous-button', {
                template: '#post-page-previous-button-template',
                props: {
                    pageInfo: Object,
                },
                data () {
                    return {
                        scrollToPage: window.sharedDataBindings.scrollToPage
                    }
                },
                directives: {
                    'scroll-to-page': function (el, binding, vnode) {
                        let pageRenderVue = vnode.context;
                        if (binding.value.currentPage === binding.value.scrollToPage) {
                            pageRenderVue.$nextTick(() => el.scrollIntoView()); // when page haven't been requested before, new pageRenderVue.$el is not ready
                            window.sharedDataBindings.scrollToPage.value = 0; // reset for next time scroll with same scrollToPage
                        }
                    }
                },
                methods: {
                    loadPage (pageNum) {
                        this.$data.scrollToPage.value = pageNum;
                        this.$emit('load-page', pageNum);
                    }
                }
            });

            const postPageNextButtonComponent = Vue.component('post-page-next-button', {
                template: '#post-page-next-button-template',
                props: {
                    currentPage: Number
                },
                data () {
                    return {
                        scrollToPage: window.sharedDataBindings.scrollToPage
                    }
                },
                methods: {
                    loadPage (pageNum) {
                        this.$data.scrollToPage.value = pageNum;
                        this.$emit('load-page', pageNum);
                    }
                }
            });

            const postsRenderListComponent = Vue.component('post-render-list', {
                template: '#post-render-list-template',
                props: {
                    posts: Object
                },
                mounted () {
                    this.$props.posts = this.groupSubRepliesByAuthor(this.$props.posts);
                },
                methods: {
                    groupSubRepliesByAuthor (postsData) {
                        postsData.threads = _.map(postsData.threads, (thread) => {
                            thread.replies = _.map(thread.replies, (reply) => {
                                reply.subReplies = _.reduce(reply.subReplies, (groupedSubReplies, subReply, index, subReplies) => {
                                    // group sub replies item by continuous and same author info
                                    let previousSubReply = subReplies[index - 1];
                                    if (previousSubReply !== undefined
                                        && subReply.authorUid === previousSubReply.authorUid
                                        && subReply.authorManagerType === previousSubReply.authorManagerType
                                        && subReply.authorExpGrade === previousSubReply.authorExpGrade) {
                                        _.last(groupedSubReplies).push(subReply);
                                    } else {
                                        groupedSubReplies.push([subReply]);
                                    }
                                    return groupedSubReplies;
                                }, []);
                                return reply;
                            });
                            return thread;
                        });
                        return postsData;
                    }
                }
            });

            const postsRenderTableComponent = Vue.component('post-render-table', {
                template: '#post-render-table-template',
                props: {
                    posts: Object
                },
                data () {
                    return {
                        threads: [],
                        threadsReply: [],
                        repliesSubReply: [],
                        threadColumns: [
                            { title: 'tid', dataIndex: 'tid' },
                            { title: '回复量', dataIndex: 'replyNum' },
                            { title: '浏览量', dataIndex: 'viewNum' },
                            { title: '分享量', dataIndex: 'shareNum' },
                            { title: '发帖人UID', dataIndex: 'authorUid' },
                            { title: '发帖人吧务级别', dataIndex: 'authorManagerType' },
                            { title: '发帖时间', dataIndex: 'postTime' },
                            { title: '最后回复人UID', dataIndex: 'latestReplierUid' },
                            { title: '最后回复时间', dataIndex: 'latestReplyTime' },
                            { title: '1楼pid', dataIndex: 'firstPid' },
                            { title: '精品', dataIndex: 'isGood' },
                            { title: '置顶', dataIndex: 'stickyType' },
                            { title: '主题贴类型', dataIndex: 'threadType' },
                            { title: '话题贴类型', dataIndex: 'topicType' },
                            { title: '赞踩量', dataIndex: 'agreeInfo' },
                            { title: '旧版赞踩量', dataIndex: 'zanInfo' },
                            { title: '发帖位置', dataIndex: 'location' },
                            { title: '首次收录时间', dataIndex: 'created_at' },
                            { title: '最后更新时间', dataIndex: 'updated_at' }
                        ],
                        replyColumns: [
                            { title: 'pid', dataIndex: 'pid' },
                            { title: '楼层', dataIndex: 'floor' },
                            { title: '楼中楼回复量', dataIndex: 'subReplyNum' },
                            { title: '发帖人UID', dataIndex: 'authorUid' },
                            { title: '发帖人吧务级别', dataIndex: 'authorManagerType' },
                            { title: '发帖人经验等级', dataIndex: 'authorExpGrade' },
                            { title: '发帖时间', dataIndex: 'postTime' },
                            { title: '是否折叠', dataIndex: 'isFold' },
                            { title: '赞踩量', dataIndex: 'agreeInfo' },
                            { title: '客户端小尾巴', dataIndex: 'signInfo' },
                            { title: '发帖来源', dataIndex: 'tailInfo' },
                            { title: '发帖位置', dataIndex: 'location' },
                            { title: '首次收录时间', dataIndex: 'created_at' },
                            { title: '最后更新时间', dataIndex: 'updated_at' }
                        ],
                        subReplyColumns: [
                            { title: 'spid', dataIndex: 'spid' },
                            { title: '发帖人UID', dataIndex: 'authorUid' },
                            { title: '发帖人吧务级别', dataIndex: 'authorManagerType' },
                            { title: '发帖人经验等级', dataIndex: 'authorExpGrade' },
                            { title: '发帖时间', dataIndex: 'postTime' },
                            { title: '首次收录时间', dataIndex: 'created_at' },
                            { title: '最后更新时间', dataIndex: 'updated_at' }
                        ]
                    };
                },
                mounted () {
                    this.$data.threads = this.$props.posts.threads;
                    this.$data.threadsReply = _.chain(this.$data.threads)
                        .map('replies')
                        .reject(_.isEmpty) // remove threads which haven't reply
                        .mapKeys((replies) => replies[0].tid) // convert threads' reply array to object for adding tid key
                        .value();
                    this.$data.repliesSubReply = _.chain(this.$data.threadsReply)
                        .toArray() // tid keyed object to array
                        .flatten() // flatten every thread's replies
                        .map('subReplies')
                        .reject(_.isEmpty) // remove replies which haven't sub reply
                        .mapKeys((subReplies) => subReplies[0].pid) // convert replies' sub reply array to object for adding pid key
                        .value();
                }
            });

            const postsRenderRawComponent = Vue.component('post-render-raw', {
                template: '#post-render-raw-template',
                props: {
                    posts: Object
                }
            });

            const loadingListPlaceholderComponent = Vue.component('loading-list-placeholder', {
                template: '#loading-list-placeholder-template'
            });

            const errorPlaceholderComponent = Vue.component('error-placeholder', {
                template: '#error-placeholder-template',
                props: {
                    error: Object
                }
            });

            const inputTextMatchParamComponent = Vue.component('input-text-match-param', {
                template: '#input-text-match-param-template',
                model: {
                    prop: 'param'
                },
                props: {
                    param: Object,
                    paramIndex: Number,
                    classes: Object
                },
                methods: {
                    modelEvent (name, value) {
                        this.$props.param.subParam[name] = value;
                        this.$emit('input', this.$props.param);
                    }
                }
            });

            const inputNumericParamComponent = Vue.component('input-numeric-param', {
                template: '#input-numeric-param-template',
                model: {
                    prop: 'param'
                },
                props: {
                    param: Object,
                    classes: Object,
                    placeholders: Object
                },
                methods: {
                    modelEvent (value) {
                        this.$props.param.value = value;
                        this.$emit('input', this.$props.param);
                    }
                }
            });

            const selectRangeComponent = Vue.component('select-range', {
                template: '#select-range-template',
                props: {
                    value: String
                }
            });

            const selectParamComponent = Vue.component('select-param', {
                template: '#select-param-template',
                props: {
                    currentParam: String
                },
                data () {
                    return {
                        paramsGroup: {
                            贴子ID: {
                                tid: 'tid（主题贴ID）',
                                pid: 'pid（回复贴ID）',
                                spid: 'spid（楼中楼ID）'
                            },
                            所有贴子类型: {
                                postTime: '发帖时间',
                                authorUid: '发帖人UID',
                                authorName: '发帖人用户名',
                                authorDisplayName: '发帖人覆盖名',
                                authorGender: '发帖人性别',
                                authorManagerType: '发帖人吧务级别'
                            },
                            仅主题贴: {
                                latestReplyTime: '最后回复时间',
                                threadTitle: '主题贴标题',
                                threadViewNum: '主题贴浏览量',
                                threadShareNum: '主题贴分享量',
                                threadReplyNum: '主题贴回复量',
                                threadProperties: '主题贴属性',
                                latestReplierUid: '最后回复人UID',
                                latestReplierName: '最后回复人用户名',
                                latestReplierDisplayName: '最后回复人覆盖名',
                                latestReplierGender: '最后回复人性别'
                            },
                            仅回复贴: {
                                replySubReplyNum: '楼中楼回复量'
                            },
                            仅回复贴或楼中楼: {
                                postContent: '贴子内容',
                                authorExpGrade: '发帖人经验等级'
                            }
                        }
                    };
                }
            });

            const baseQueryFormMixin = {
                template: '#query-form-template',
                data () {
                    return {
                        antd,
                        isRequesting: false,
                        uniqueParams: {},
                        params: [], // [{ name: '', value: '', subParam: { name: value } },...]
                        invalidParamsIndex: [],
                        paramsDefaultValue: {},
                        paramsPreprocessor: {},
                        paramsWatcher: {},
                        paramWatcher (newParamsArray, oldParamsArray) {
                            _.each(_.filter(newParamsArray, (param) => _.includes(_.keys(this.$data.paramsWatcher), param.name)), (param) => {
                                this.$data.paramsWatcher[param.name](param);
                            })
                        }
                    };
                },
                watch: {
                    uniqueParams: { handler: 'paramWatcher', deep: true },
                    params: { handler: 'paramWatcher', deep: true },
                    $route: function (to, from) {
                        if (_.isEqual(to.path, from.path)) {
                            return; // ignore when only hash has changed
                        }
                        if (! this.$data.isRequesting) { // isRequesting will be false when route change is not emit by <query-form>.submit()
                            // these query logic is for route changes which is not trigger by <query-form>.submit(), such as user emitted history.back() or go()
                            let isOnlyPageChanged = _.isEqual(_.omit(to.params, 'page'), _.omit(from.params, 'page'));
                            this.parseRoute(to);
                            if (this.checkParams()) {
                                this.$data.isRequesting = true;
                                this.$emit('query', { queryParams: this.flattenParams(), shouldReplacePage: ! isOnlyPageChanged });
                            }
                        }
                    }
                },
                beforeMount () {
                    this.$data.uniqueParams = _.mapValues(this.$data.uniqueParams, _.unary(this.fillParamWithDefaultValue));
                    this.$data.params = _.map(this.$data.params, _.unary(this.fillParamWithDefaultValue));
                },
                mounted () {
                    this.parseRoute(this.$route); // first time parse
                    if (this.checkParams()) { // query manually since route update event can't be triggered while first load
                        this.$data.isRequesting = true;
                        this.$emit('query', { queryParams: this.flattenParams(), shouldReplacePage: true });
                    }
                },
                methods: {
                    submit () {
                        if (this.checkParams()) { // check here to stop route submit
                            this.$data.isRequesting = true;
                            let beforeRoutePath = this.$route.path;
                            this.submitRoute();
                            this.$emit('query', { queryParams: this.flattenParams(), shouldReplacePage: true }); // force emit event to refresh new query since route update event won't emit when isRequesting is true
                        }
                    },
                    parseRoute (route) { throw('component must implements mixin abstruct method') },
                    checkParams () { throw('component must implements mixin abstruct method') },
                    submitRoute () { throw('component must implements mixin abstruct method') },
                    paramRowLastDOMClass (paramIndex, params) {
                        return params.length === 1 ? {} : { // if it's the only row, class remains unchanged
                            'param-control-first-row': paramIndex === 0,
                            'param-control-middle-row': ! (paramIndex === 0 || paramIndex === params.length - 1),
                            'param-control-last-row': paramIndex === params.length - 1
                        }
                    },
                    escapeParamValue (value, unescape = false) {
                        if (_.isString(value)) {
                            _.map({ // we don't escape ',' since array type params is already known
                                '/': '%2F',
                                ';': '%3B'
                            }, (encode, char) => value = value.replace(unescape ? encode : char, unescape ? char : encode));
                        }
                        return value;
                    },
                    addParam (event) {
                        this.$data.params.push(this.fillParamWithDefaultValue({ name: event.target.value }));
                        event.target.value = 'add'; // reset to add option
                    },
                    changeParam (beforeParamIndex, afterParamName) {
                        _.pull(this.$data.invalidParamsIndex, beforeParamIndex);
                        this.$set(this.$data.params, beforeParamIndex, this.fillParamWithDefaultValue({ name: afterParamName }));
                    },
                    deleteParam (paramIndex) {
                        _.pull(this.$data.invalidParamsIndex, paramIndex);
                        this.$data.invalidParamsIndex = _.map(this.$data.invalidParamsIndex, (invalidParamIndex) => invalidParamIndex > paramIndex ? invalidParamIndex - 1 : invalidParamIndex) // move forward params index which is after current one
                        this.$delete(this.$data.params, paramIndex);
                    },
                    fillParamWithDefaultValue (param, resetToDefault = false) {
                        if (resetToDefault) { // cloneDeep to prevent defaultsDeep mutates origin object
                            return _.defaultsDeep(_.cloneDeep(this.$data.paramsDefaultValue[param.name]), param);
                        } else {
                            return _.defaultsDeep(_.cloneDeep(param), this.$data.paramsDefaultValue[param.name]);
                        }
                    },
                    clearParamDefaultValue (param) {
                        param = _.cloneDeep(param); // prevent changing origin param
                        let defaultParam = this.$data.paramsDefaultValue[param.name];
                        if (defaultParam === undefined) {
                            if (_.isEmpty(param.subParam)) {
                                delete param.subParam;
                            }
                            return param;
                        }
                        if (! (_.isNumber(param.value) || ! _.isEmpty(param.value)) // number is consider as empty in isEmpty(), to prevent this here we use complex boolean expression
                            || (_.isArray(param.value)
                                ? _.isEqual(_.sortBy(param.value), _.sortBy(defaultParam.value)) // sort array type param value for comparing
                                : param.value === defaultParam.value)) {
                            delete param.value;
                        }
                        _.each(defaultParam.subParam, (value, name) => {
                            if (param.subParam[name] === value || value === undefined) { // undefined means this sub param must be deleted, as part of the parent param value
                                delete param.subParam[name];
                            }
                        });
                        if (_.isEmpty(param.subParam)) {
                            delete param.subParam;
                        }
                        return _.isEqual(_.keys(param), ['name']) ? null : param;  // return null for further filter()
                    },
                    clearedParamsDefaultValue () {
                        return _.filter(_.map(this.$data.params, this.clearParamDefaultValue)); // filter() will remove falsy values like null
                    },
                    clearedUniqueParamsDefaultValue (...omitParams) {
                        return _.pickBy(_.mapValues(_.omit(this.$data.uniqueParams, omitParams), this.clearParamDefaultValue)); // mapValues() return object which remains keys, pickBy() like filter() for objects
                    },
                    parseParamRoute (routePath) {
                        _.chain(routePath)
                            .trim('/')
                            .split('/')
                            .filter() // filter() will remove falsy values like ''
                            .map((paramWithSub) => {
                                let parsedParam = { subParam: {} };
                                _.each(paramWithSub.split(';'), (params, paramIndex) => { // split multiple params
                                    let paramPair = [params.substr(0, params.indexOf(':')), this.escapeParamValue(params.substr(params.indexOf(':') + 1), true)]; // split kv pair by first colon, using substr to prevent split array type param value
                                    if (paramIndex === 0) { // main param
                                        [parsedParam.name, parsedParam.value] = paramPair;
                                    } else { // sub params
                                        parsedParam.subParam[paramPair[0]] = paramPair[1];
                                    }
                                })
                                return parsedParam;
                            })
                            .map(_.unary(this.fillParamWithDefaultValue))
                            .each((param) => {
                                if (_.includes(_.keys(this.$data.paramsPreprocessor), param.name)) {
                                    this.$data.paramsPreprocessor[param.name](param);
                                }
                                if (_.includes(_.keys(this.$data.uniqueParams), param.name)) { // is unique param
                                    this.$data.uniqueParams[param.name] = param;
                                } else {
                                    this.$data.params.push(param);
                                }
                            })
                            .value()
                    },
                    submitParamRoute (filteredUniqueParams, filteredParams) {
                        this.$router.push({ path: `/${_.chain([..._.values(filteredUniqueParams), ...filteredParams])
                            .map((param) => `${param.name}:${this.escapeParamValue(_.isArray(param.value) ? param.value.join(',') : param.value)}${_.map(param.subParam, (value, name) => `;${name}:${this.escapeParamValue(value)}`).join('')}`)  // format param to route path, e.g. name:value;subParamName:subParamValue...
                            .join('/')
                            .value()}` });
                    },
                    flattenParams () {
                        const flattenParam = (param) => {
                            let flatted = {};
                            flatted[param.name] = param.value;
                            return { ...flatted, ...param.subParam };
                        };
                        return [
                            ..._.map(_.values(this.clearedUniqueParamsDefaultValue()), flattenParam),
                            ..._.map(this.clearedParamsDefaultValue(), flattenParam)
                        ];
                    }
                }
            };

            const queryFormComponent = Vue.component('query-form', {
                mixins: [baseQueryFormMixin],
                props: {
                    forumList: Array
                },
                data () {
                    return {
                        uniqueParams: {
                            fid: { name: 'fid' },
                            postTypes: { name: 'postTypes' },
                            orderBy: { name: 'orderBy' }
                        },
                        paramsDefaultValue: {
                            numericParams: { subParam: { range: '=' } },
                            textMatchParmas: { subParam: { matchBy: 'implicit', spaceSplit: false } },
                            fid: { value: 'NULL' },
                            postTypes: { value: ['thread', 'reply', 'subReply'] },
                            orderBy: { value: 'default', subParam: { direction: 'default' } },
                            get tid () { return this.numericParams },
                            get pid () { return this.numericParams },
                            get spid () { return this.numericParams },
                            postTime: { subParam: { range: undefined } },
                            latestReplyTime: { subParam: { range: undefined } },
                            get threadTitle () { return this.textMatchParmas },
                            get postContent () { return this.textMatchParmas },
                            get threadViewNum () { return this.numericParams },
                            get threadShareNum () { return this.numericParams },
                            get threadReplyNum () { return this.numericParams },
                            get replySubReplyNum () { return this.numericParams },
                            threadProperties: { value: [] },
                            get authorUid () { return this.numericParams },
                            get authorName () { return this.textMatchParmas },
                            get authorDisplayName () { return this.textMatchParmas },
                            get authorExpGrade () { return this.numericParams },
                            get latestReplierUid () { return this.numericParams },
                            get latestReplierName () { return this.textMatchParmas },
                            get latestReplierDisplayName () { return this.textMatchParmas }
                        },
                        paramsRequiredPostTypes: {
                            pid: [['reply', 'subReply'], 'OR'],
                            spid: [['subReply'], 'AND'],
                            latestReplyTime: [['thread'], 'AND'],
                            threadTitle: [['thread'], 'AND'],
                            postContent: [['reply', 'subReply'], 'OR'],
                            threadViewNum: [['thread'], 'AND'],
                            threadShareNum: [['thread'], 'AND'],
                            threadReplyNum: [['thread'], 'AND'],
                            replySubReplyNum: [['reply'], 'AND'],
                            threadProperties: [['thread'], 'AND'],
                            authorExpGrade: [['reply', 'subReply'], 'OR'],
                            latestReplierUid: [['thread'], 'AND'],
                            latestReplierName: [['thread'], 'AND'],
                            latestReplierDisplayName: [['thread'], 'AND'],
                            latestReplierGender: [['thread'], 'AND']
                        },
                        orderByRequiredPostTypes :{
                            tid: [['thread', 'reply', 'subReply'], 'OR'],
                            pid: [['reply', 'subReply'], 'OR'],
                            spid: [['subReply'], 'OR']
                        },
                        isOrderByInvalid: false,
                        isFidInvalid: false,
                        paramsPreprocessor: { // param is byref object so changes will sync
                            dateTimeRangeParams (param) {
                                param.subParam.range = param.value.split(',');
                            },
                            arrayTypeParams (param) {
                                param.value = param.value.split(',');
                            },
                            textMatchParams (param) {
                                param.subParam.spaceSplit = param.subParam.spaceSplit === 'true'; // literal string to bool convert
                            },
                            get postTypes () { return this.arrayTypeParams },
                            get postTime () { return this.dateTimeRangeParams },
                            get latestReplyTime () { return this.dateTimeRangeParams },
                            get threadProperties () { return this.arrayTypeParams },
                            get threadTitle () { return this.textMatchParams },
                            get postContent () { return this.textMatchParams },
                            get authorName () { return this.textMatchParams },
                            get authorDisplayName () { return this.textMatchParams },
                            get latestReplierName () { return this.textMatchParams },
                            get latestReplierDisplayName () { return this.textMatchParams }
                        },
                        paramsWatcher: { // param is byref object so changes will sync
                            dateTimeRangeParams (param) {
                                param.value = (param.subParam.range || []).join(','); // combine datetime range into root param's value
                            },
                            textMatchParams (param) {
                                if (param.subParam.matchBy === 'regex') {
                                    param.subParam.spaceSplit = false;
                                }
                            },
                            get postTime () { return this.dateTimeRangeParams },
                            get latestReplyTime ()  { return this.dateTimeRangeParams },
                            get threadTitle () { return this.textMatchParams },
                            get postContent () { return this.textMatchParams },
                            get authorName () { return this.textMatchParams },
                            get authorDisplayName () { return this.textMatchParams },
                            get latestReplierName () { return this.textMatchParams },
                            get latestReplierDisplayName () { return this.textMatchParams },
                            orderBy (param) {
                                if (param.value === 'default') { // reset to default
                                    param.subParam.direction = 'default';
                                }
                            }
                        }
                    };
                },
                methods: {
                    currentQueryType () {
                        let clearedParams = this.clearedParamsDefaultValue();
                        if (_.isEmpty(clearedParams)) { // is there no other params
                            if (_.isEmpty(this.clearedUniqueParamsDefaultValue('postTypes'))) {
                                return 'empty';
                            } else if (_.isEmpty(this.clearedUniqueParamsDefaultValue('fid', 'postTypes'))) { // note when query with postTypes param, the route will goto params not the fid
                                return 'fid';
                            }
                        }
                        if (_.isEmpty(_.reject(clearedParams, (param) => _.includes(['tid', 'pid', 'spid'], param.name))) // is there no other params
                            && _.filter(clearedParams, (param) => _.includes(['tid', 'pid', 'spid'], param.name)).length === 1) { // is there only one post id param
                            return 'postID';
                        }
                        return 'search';
                    },
                    parseRoute (route) {
                        this.$data.uniqueParams = _.mapValues(this.$data.uniqueParams, _.unary(this.fillParamWithDefaultValue));
                        this.$data.params = [];
                        // parse route path to params
                        if (route.name.startsWith('param')) {
                            this.parseParamRoute(route.params.pathMatch); // omit page param from route full path
                        } else if (route.name.startsWith('fid')) {
                            this.$data.uniqueParams.fid.value = route.params.fid;
                        } else { // post id routes
                            this.$data.params = _.map(_.omit(route.params, 'pathMatch', 'page'), (value, name) => this.fillParamWithDefaultValue({ name, value }) );
                        }
                    },
                    checkParams () {
                        // check query type
                        this.$data.isFidInvalid = false;
                        let clearedUniqueParams = this.clearedUniqueParamsDefaultValue();
                        switch (this.currentQueryType()) {
                            case 'empty':
                                new Noty({ timeout: 3000, type: 'warning', text: '请选择贴吧或/并输入查询参数，勿只选择贴子类型参数'}).show();
                                return false; // exit early
                            case 'postID':
                                if (clearedUniqueParams.fid !== undefined) {
                                    this.$data.uniqueParams.fid.value = this.$data.paramsDefaultValue.fid.value; // reset fid to default value
                                    new Noty({ timeout: 3000, type: 'info', text: '已移除按贴索引查询所不需要的查询贴吧参数'}).show();
                                    this.submitRoute(); // update route to match new params without fid
                                }
                                return true; // index query doesn't restrict on post types
                            case 'search':
                                if (clearedUniqueParams.fid === undefined) {
                                    this.$data.isFidInvalid = true; // search query require fid param
                                    new Noty({ timeout: 3000, type: 'warning', text: '搜索查询必须指定查询贴吧'}).show();
                                }
                                break;
                        }
                        // check params required post types
                        let postTypes = _.sortBy(this.$data.uniqueParams.postTypes.value);
                        this.$data.invalidParamsIndex = []; // reset to prevent duplicate indexes
                        _.each(_.map(this.$data.params, this.clearParamDefaultValue), (param, paramIndex) => { // we don't filter() here for post types validate
                            if (param !== null && param.value != null) { // is param have no diff with default value and have value
                                let paramRequiredPostTypes = this.$data.paramsRequiredPostTypes[param.name];
                                if (paramRequiredPostTypes !== undefined) { // not set means this param accepts any post types
                                    if (! (paramRequiredPostTypes[1] === 'OR' // does uniqueParams.postTypes fits with params required post types
                                        ? _.isEmpty(_.difference(postTypes, _.sortBy(paramRequiredPostTypes[0])))
                                        : _.isEqual(_.sortBy(paramRequiredPostTypes[0]), postTypes))) {
                                        this.$data.invalidParamsIndex.push(paramIndex);
                                    }
                                }
                            } else {
                                this.$data.invalidParamsIndex.push(paramIndex);
                            }
                        });
                        if (! _.isEmpty(this.$data.invalidParamsIndex)) {
                            new Noty({ timeout: 3000, type: 'warning', text: `第${_.map(this.$data.invalidParamsIndex, (i) => i + 1).join(',')}项查询参数与查询贴子类型要求不匹配`}).show();
                        }
                        // check order by required post types
                        this.$data.isOrderByInvalid = false;
                        let orderBy = this.$data.uniqueParams.orderBy.value;
                        if (_.includes(_.keys(this.$data.orderByRequiredPostTypes), orderBy)) {
                            let orderByRequiredPostTypes = this.$data.orderByRequiredPostTypes[orderBy];
                            if (! (orderByRequiredPostTypes[1] === 'OR'
                                ? _.isEmpty(_.difference(postTypes, _.sortBy(orderByRequiredPostTypes[0])))
                                : _.isEqual(_.sortBy(orderByRequiredPostTypes[0]), postTypes))) {
                                this.$data.isOrderByInvalid = true;
                                new Noty({ timeout: 3000, type: 'warning', text: '排序方式与查询贴子类型要求不匹配'}).show();
                            }
                        }

                        return _.isEmpty(this.$data.invalidParamsIndex) && ! this.$data.isOrderByInvalid && ! this.$data.isFidInvalid; // return false when there's any invalid params
                    },
                    submitRoute () {
                        // decide which route to go
                        let clearedParams = this.clearedParamsDefaultValue();
                        let clearedUniqueParams = this.clearedUniqueParamsDefaultValue();
                        if (_.isEmpty(clearedUniqueParams)) { // might be post id route
                            for (const postIDName of ['spid', 'pid', 'tid']) { // todo: sub posts id goes first to simply verbose multi post id condition
                                let postIDParam = _.filter(clearedParams, (param) => param.name === postIDName);
                                let isOnlyOnePostIDParam = _.isEmpty(_.reject(clearedParams, (param) => param.name === postIDName)) // is there no other params
                                    && postIDParam.length === 1 // is there only one post id param
                                    && postIDParam[0].subParam == null; // is range subParam not set
                                if (isOnlyOnePostIDParam) {
                                    this.$router.push({ name: postIDName, params: { [postIDName]: postIDParam[0].value } });
                                    return; // exit early to prevent pushing other route
                                }
                            }
                        }
                        if (clearedUniqueParams.fid !== undefined && _.isEmpty(clearedParams) && _.isEmpty(_.omit(clearedUniqueParams, 'fid'))) { // fid route
                            this.$router.push({ name: 'fid', params: { fid: clearedUniqueParams.fid.value } });
                            return;
                        }
                        this.submitParamRoute(clearedUniqueParams, clearedParams); // param route
                    }
                }
            });

            const postsQueryComponent = Vue.component('posts-query', {
                template: '#posts-query-template',
                data () {
                    return {
                        forumList: [],
                        postPages: [],
                        currentRoutesPage: parseInt(this.$route.params.page) || 1,
                        queryError: null,
                        renderType: 'table'
                    };
                },
                watch: {
                    $route: function (to, from) {
                        this.$data.currentRoutesPage = parseInt(to.params.page) || 1;
                    }
                },
                beforeMount () {
                    $$loadForumList().then((forumList) => this.$data.forumList = forumList);
                },
                methods: {
                    loadPage (pageNum) {
                        this.$router.push(this.$route.name.startsWith('param')
                            ? { path: `/page/${pageNum}/${this.$route.params.pathMatch}` }
                            : {
                                name: `${this.$route.name}${this.$route.name.endsWith('+p') ? '' : '+p'}`,
                                params: { ...this.$route.params, page: pageNum }
                            });
                    },
                    query ({ queryParams, shouldReplacePage }) {
                        this.$data.queryError = null;
                        if (shouldReplacePage) {
                            this.$data.postPages = []; // clear posts pages data before request to show loading placeholder
                        } else {
                            if (! _.isEmpty(_.filter(_.map(this.$data.postPages, 'pages.currentPage'), (i) => i === this.$data.currentRoutesPage))) {
                                this.$refs.queryForm.$data.isRequesting = false;
                                return; // cancel request when requesting page have already been loaded
                            }
                        }

                        let ajaxStartTime = Date.now();
                        if (window.$previousPostsQueryAjax != null) { // cancel previous pending ajax to prevent conflict
                            window.$previousPostsQueryAjax.abort();
                        }
                        $$reCAPTCHACheck().then((reCAPTCHA) => {
                            window.$previousPostsQueryAjax = $.getJSON(`${$$baseUrl}/api/postsQuery`, $.param({ query: JSON.stringify(queryParams), page: this.$data.currentRoutesPage, reCAPTCHA}));
                            window.$previousPostsQueryAjax
                                .done((ajaxData) => {
                                    if (shouldReplacePage) { // is loading next page data on the same query params or requesting new query with different params
                                        this.$data.postPages = [ajaxData];
                                    } else {
                                        // insert after existing previous page, if not exist will be inserted at start
                                        this.$data.postPages.splice(_.findIndex(this.$data.postPages, { pages: { currentPage: this.$data.currentRoutesPage - 1 } }) + 1, 0, ajaxData);
                                    }
                                    new Noty({ timeout: 3000, type: 'success', text: `已加载第${ajaxData.pages.currentPage}页 ${ajaxData.pages.currentItems}条贴子 耗时${Date.now() - ajaxStartTime}ms`}).show();
                                    //this.changeDocumentTitle(this.$route);
                                })
                                .fail((jqXHR) => {
                                    this.$data.postPages = [];
                                    if (jqXHR.responseJSON != null) {
                                        let error = jqXHR.responseJSON;
                                        if (_.isObject(error.errorInfo)) { // response when laravel failed validate, same with ajaxError jquery event @ layout.blade.php
                                            this.$data.queryError = { code: error.errorCode, info: _.map(error.errorInfo, (info, paramName) => `参数 ${paramName}：${info.join('<br />')}`).join('<br />') };
                                        } else {
                                            this.$data.queryError = { code: error.errorCode, info: error.errorInfo };
                                        }
                                    }
                                })
                                .always(() => this.$refs.queryForm.$data.isRequesting = false);
                        });
                    }
                }
            });

            const postsQueryVue = new Vue({
                el: '#posts-query',
                router: new VueRouter({
                    mode: 'history',
                    base: `${$$baseUrlDir}/postMulti`,
                    routes: [
                        {
                            name: 'root',
                            path: '/',
                            component: postsQueryComponent,
                            children: [
                                { name: 'fid', path: 'fid/:fid' },
                                { name: 'fid+p', path: 'fid/:fid/page/:page' },
                                { name: 'tid', path: 'tid/:tid' },
                                { name: 'tid+p', path: 'tid/:tid/page/:page' },
                                { name: 'pid', path: 'pid/:pid' },
                                { name: 'pid+p', path: 'pid/:pid/page/:page' },
                                { name: 'spid', path: 'spid/:spid' },
                                { name: 'spid+p', path: 'spid/:spid/page/:page' },
                                { name: 'param+p', path: 'page/:page/*' },
                                { name: 'param', path: '*' },
                            ]
                        }
                    ]
                })
            });
        </script>
    @endverbatim
@endsection
