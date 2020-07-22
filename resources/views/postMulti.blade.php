@extends('layout')
@include('module.bootstrapCallout')
@include('module.tiebaPostContentElement')
@include('module.vue.scrollList')
@include('module.vue.tiebaSelectUser')
@include('module.vue.antd')

@section('title', '贴子查询')

@section('style')
    <style>
        .query-param-row {
            margin-top: -1px;
        }

        .select-param.is-invalid {
            z-index: 1; /* let border overlaps other selects */
        }
        .select-param-first-row {
            border-top-left-radius: 0.25rem !important;
        }
        .select-param-last-row {
            border-bottom-left-radius: 0.25rem !important;
        }
        .param-control-first-row {
            border-bottom-right-radius: 0 !important;
        }
        .param-control-middle-row {
            border-bottom-right-radius: 0 !important;
            border-top-right-radius: 0 !important;
        }
        .param-control-last-row {
            border-top-right-radius: 0 !important;
        }

        .add-param-button { /* fa-plus is wider than fa-times 3px */
            padding-left: 10px;
            padding-right: 11px;
        }

        .a-datetime-range {
            margin-left: -1px;
        }
        .ant-calendar-picker-input {
            height: 38px;
            border-bottom-left-radius: 0;
            border-top-left-radius: 0;
        }
    </style>
@endsection

@section('container')
    @verbatim
        <template id="select-range-template">
            <select :value="value" @input="$emit('input', $event.target.value)" class="col-1 custom-select form-control">
                <option>&lt;</option>
                <option>=</option>
                <option>&gt;</option>
                <option>IN</option>
                <option>BETWEEN</option>
            </select>
        </template>
        <template id="select-param-template">
            <select @change="$emit('param-change', $event)" class="col-2 custom-select form-control">
                <option selected="selected" value="add" disabled>New...</option>
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
                        <select v-model.number="uniqueParams.fid.value" id="paramFid" class="custom-select form-control">
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
                        <select v-if="uniqueParams.orderBy.value !== 'default'" v-model="uniqueParams.orderBy.subParam.direction" class="col-6 custom-select form-control">
                            <option value="default">默认（按贴索引查询正序，按吧索引/搜索查询倒序）</option>
                            <option value="ASC">正序（从小到大，旧到新）</option>
                            <option value="DESC">倒序（从大到小，新到旧）</option>
                        </select>
                    </div>
                </div>
                <div class="query-param-row form-row" v-for="(param, paramIndex) in params">
                    <div class="input-group">
                        <button @click="deleteParam(paramIndex)" type="button" class="btn btn-link"><i class="fas fa-times"></i></button>
                        <select-param @param-change="changeParam(paramIndex, $event.target.value)" :current-param="param.name"
                            :class="{
                                'is-invalid': invalidParamsIndex.includes(paramIndex),
                                'select-param-first-row': paramIndex === 0,
                                'select-param-last-row': paramIndex === params.length - 1,
                                'select-param': true
                            }"></select-param>
                        <template v-if="_.includes(['tid', 'pid', 'spid'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input v-if="param.subParam.range === 'IN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   :placeholder="param.name === 'tid' ? '5000000000,5000000001,5000000002,...' : '15000000000,15000000001,15000000002,...'" :aria-label="param.name"
                                   type="text" class="col form-control" required pattern="\d+(,\d+)+" />
                            <input v-else-if="param.subParam.range === 'BETWEEN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   :placeholder="param.name === 'tid' ? '5000000000,6000000000' : '15000000000,16000000000'" :aria-label="param.name"
                                   type="text" class="col-3 form-control" required pattern="\d+,\d+" />
                            <input v-else v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   :placeholder="param.name === 'tid' ? 5000000000 : 15000000000" :aria-label="param.name"
                                   type="number" class="col-2 form-control" required />
                        </template>
                        <template v-if="_.includes(['postTime', 'latestReplyTime'], param.name)">
                            <a-range-picker v-model="param.subParam.range" :locale="antd.locales.zh_CN.DatePicker" :show-time="true"
                                            format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DDTHH:mm" size="large" class="a-datetime-range"></a-range-picker>
                        </template>
                        <template v-if="_.includes(['threadTitle', 'postContent'], param.name)">
                            <input v-model="param.value" type="text" placeholder="模糊匹配 非正则下空格分割关键词" class="form-control" required>
                            <div class="input-group-append">
                                <div :class="getControlRowClass(paramIndex, params)" class="input-group-text">
                                    <div class="custom-checkbox custom-control">
                                        <input v-model="param.subParam.regex" id="paramThreadTitleRegex" type="checkbox" class="custom-control-input">
                                        <label class="custom-control-label" for="paramThreadTitleRegex">正则</label>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-if="_.includes(['threadViewNum', 'threadShareNum', 'threadReplyNum', 'replySubReplyNum'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input v-if="param.subParam.range === 'IN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)" :aria-label="param.name"
                                   placeholder="100" type="text" class="col form-control" required pattern="\d+(,\d+)+" />
                            <input v-else-if="param.subParam.range === 'BETWEEN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)" :aria-label="param.name"
                                   placeholder="100" type="text" class="col-3 form-control" required pattern="\d+,\d+" />
                            <input v-else v-model="param.value" :class="getControlRowClass(paramIndex, params)" :aria-label="param.name"
                                   placeholder="100" type="number" class="col-2 form-control" required />
                        </template>
                    </div>
                </div>
                <div class="mt-1 form-group form-row">
                    <button type="button" class="add-param-button disabled btn btn-link"><i class="fas fa-plus"></i></button>
                    <select-param @param-change="addParam($event)"></select-param>
                </div>
                <div class="form-group form-row">
                    <button type="submit" class="btn btn-primary">查询</button>
                    <button class="ml-2 disabled btn btn-text" type="button" v-text="isParamsIndexQuery() ? (_.isEmpty(params) ? ( uniqueParams.fid.value === 'NULL' ? '全局索引查询' : '按吧索引查询') : '按贴索引查询') : '搜索查询'"></button>
                </div>
                <div class="form-group form-row">
                    <label class="col-1 col-form-label" for="paramPage">页数</label>
                    <input v-model="uniqueParams.page.value" id="paramPage" type="number" class="col-1 form-control" aria-label="page" />
                    <label class="col-2 col-form-label">route page: {{ $route.params.page }}</label>
                </div>
            </form>
        </template>
        <template id="posts-query-template">
            <query-form :forum-list="forumList" ref="queryForm"></query-form>
        </template>
        <div id="posts-query">
            <router-view></router-view>
        </div>
    @endverbatim
@endsection

@section('script')
    @verbatim
        <script>
            'use strict';
            $$initialNavBar('postMulti');

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
                                postTime: '发帖时间'
                            },
                            仅主题贴: {
                                latestReplyTime: '最后回复时间',
                                threadTitle: '主题贴标题',
                                threadViewNum: '主题贴浏览量',
                                threadShareNum: '主题贴分享量',
                                threadReplyNum: '主题贴回复量'
                            },
                            仅回复贴: {
                                replySubReplyNum: '楼中楼回复量'
                            },
                            仅回复贴或楼中楼: {
                                postContent: '贴子内容'
                            }
                        }
                    };
                },
                computed: {
                },
                mounted () {
                },
                methods: {
                }
            });

            const queryFormComponent = Vue.component('query-form', {
                template: '#query-form-template',
                props: {
                    forumList: Array
                },
                data () {
                    return {
                        antd,
                        uniqueParams: {
                            fid: { name: 'fid' },
                            postTypes: { name: 'postTypes' },
                            orderBy: { name: 'orderBy' },
                            page: { name: 'page' }
                        },
                        params: [], // [{ name: '', value: '', subParam: { name: value } }, ...]
                        paramsDefaultValue: {
                            fid: { value: 'NULL' },
                            postTypes: { value: ['thread', 'reply', 'subReply'] },
                            orderBy: { value: 'default', subParam: { direction: 'default' } },
                            page: { value: 1 },
                            // up above are unique params
                            tid: { subParam: { range: '=' } },
                            pid: { subParam: { range: '=' } },
                            spid: { subParam: { range: '=' } },
                            postTime: { subParam: { range: undefined } },
                            latestReplyTime: { subParam: { range: undefined } },
                            threadTitle: { subParam: { regex: false } },
                            postContent: { subParam: { regex: false } },
                            threadViewNum: { subParam: { range: '=' } },
                            threadShareNum: { subParam: { range: '=' } },
                            threadReplyNum: { subParam: { range: '=' } },
                            replySubReplyNum: { subParam: { range: '=' } },

                            threadProperties: { value: [] },
                            userManagerTypes: { value: 'default' },
                            userGender: { value: 'default' },
                            userExpGrade: { subParam: { range: '=' } }
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
                        },
                        orderByRequiredPostTypes :{
                            tid: [['thread', 'reply', 'subReply'], 'OR'],
                            pid: [['reply', 'subReply'], 'OR'],
                            spid: [['subReply'], 'OR']
                        },
                        invalidParamsIndex: [],
                        isOrderByInvalid: false,
                        paramsPreprocessor: { // param is byref object so changes will sync
                            dateTimeRangeParams (param) {
                                param.subParam.range = param.value.split(',');
                            },
                            postTypes (param) {
                                param.value = param.value.split(',');
                            },
                            get postTime () { return this.dateTimeRangeParams },
                            get latestReplyTime () { return this.dateTimeRangeParams }
                        },
                        paramsWatcher: { // param is byref object so changes will sync
                            dateTimeRangeParams (param) {
                                param.value = (param.subParam.range || []).join(','); // combine datetime range into root param's value
                            },
                            orderBy (param) {
                                if (param.value === 'default') { // reset to default
                                    param.subParam.direction = 'default';
                                }
                            },
                            get postTime () { return this.dateTimeRangeParams },
                            get latestReplyTime ()  { return this.dateTimeRangeParams }
                        },
                        paramWatcher (newParamsArray, oldParamsArray) {
                            console.log(JSON.stringify(newParamsArray), JSON.stringify(oldParamsArray));
                            _.each(_.filter(newParamsArray, (param) => _.includes(_.keys(this.$data.paramsWatcher), param.name)), (param) => {
                                this.$data.paramsWatcher[param.name](param);
                            })
                        }
                    };
                },
                computed: {
                },
                watch: {
                    uniqueParams: {
                        handler: 'paramWatcher',
                        deep: true
                    },
                    params: {
                        handler: 'paramWatcher',
                        deep: true
                    }
                },
                beforeMount () {
                    this.$data.uniqueParams = _.mapValues(this.$data.uniqueParams, (param) => this.fillParamWithDefaultValue(param));
                    this.$data.params = _.map(this.$data.params, (param) => this.fillParamWithDefaultValue(param));
                },
                mounted () {
                    // parse route path to params
                    if (this.$route.name.startsWith('param')) {
                        _.chain(this.$route.params.page == null ? this.$route.path : this.$route.params[1]) // when page is a route param, remaining params path will be params[1]
                            .trim('/')
                            .split('/')
                            .filter() // filter() will remove falsy values like ''
                            .map((paramWithSub) => {
                                let parsedParam = { subParam: {} };
                                _.each(paramWithSub.split(';'), (params, paramIndex) => {
                                    let paramPair = [params.substr(0, params.indexOf(':')), params.substr(params.indexOf(':') + 1)]; // split kv pair by first :
                                    if (paramIndex === 0) { // main param
                                        [parsedParam.name, parsedParam.value] = paramPair;
                                    } else { // sub params
                                        parsedParam.subParam[paramPair[0]] = paramPair[1];
                                    }
                                })
                                return parsedParam;
                            })
                            .map(this.fillParamWithDefaultValue)
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
                    } else if (this.$route.name.startsWith('fid')) {
                        this.$data.uniqueParams.fid.value = this.$route.params.fid;
                    } else { // post id routes
                        this.$data.params = _.map(_.omit(this.$route.params, 'pathMatch', 'page'), (value, name) => this.fillParamWithDefaultValue({ name, value }) );
                    }
                    this.$data.uniqueParams.page.value = this.$route.params.page || this.$data.uniqueParams.page.value;
                },
                methods: {
                    getControlRowClass (paramIndex, params) {
                        return params.length === 1 ? {} : { // if it's the only row, class remains unchanged
                            'param-control-first-row': paramIndex === 0,
                            'param-control-middle-row': ! (paramIndex === 0 || paramIndex === params.length - 1),
                            'param-control-last-row': paramIndex === params.length - 1
                        }
                    },
                    isParamsIndexQuery () { // is there only post id params
                        return _.isEmpty(_.filter(this.$data.params, (param) => ! _.includes(['tid', 'pid', 'spid'], param.name)));
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
                    fillParamWithDefaultValue (param) {
                        return _.defaultsDeep(_.cloneDeep(param), { name: param.name, ...(this.$data.paramsDefaultValue[param.name]) });
                    },
                    clearParamDefaultValue (param) {
                        param = _.cloneDeep(param); // prevent changing origin param
                        let defaultParam = this.$data.paramsDefaultValue[param.name];
                        if (_.isEmpty(param.value) || _.isArray(param.value)
                            ? _.isEqual(_.sortBy(param.value), _.sortBy(defaultParam.value))
                            : param.value === defaultParam.value) { // sort array type param value for comparing
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
                    submit () {
                        let newRouteWithPage = {
                            name: this.$data.uniqueParams.page.value !== 1 ? '+p' : '',
                            params: { page: this.$data.uniqueParams.page.value }
                        };
                        let params = _.map(this.$data.params, this.clearParamDefaultValue); // we don't filter() here for post type validate
                        let uniqueParams = _.pickBy(_.mapValues(this.$data.uniqueParams, this.clearParamDefaultValue)); // remain keys, pickBy() like filter() for objects

                        // check params and order by required post type
                        let postTypes = _.sortBy(this.$data.uniqueParams.postTypes.value);
                        this.$data.invalidParamsIndex = []; // reset to prevent duplicate indexes
                        this.$data.isOrderByInvalid = false;
                        _.each(params, (param, paramIndex) => {
                            if (param !== null) { // is param have no diff with default value
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
                        if (! _.isEmpty(this.$data.invalidParamsIndex)) { // cancel submit when there's any invalid params
                            return;
                        }
                        params = _.filter(params); // filter() will remove falsy values like null
                        let orderBy = this.$data.uniqueParams.orderBy.value;
                        if (_.includes(_.keys(this.$data.orderByRequiredPostTypes), orderBy)) {
                            let orderByRequiredPostTypes = this.$data.orderByRequiredPostTypes[orderBy];
                            if (! (orderByRequiredPostTypes[1] === 'OR'
                                ? _.isEmpty(_.difference(postTypes, _.sortBy(orderByRequiredPostTypes[0])))
                                : _.isEqual(_.sortBy(orderByRequiredPostTypes[0]), postTypes))) {
                                this.$data.isOrderByInvalid = true;
                            }
                        }

                        // decide which route to go
                        if (_.isEmpty(_.omitBy(uniqueParams, { name: 'page' }))) { // post id route
                            for (const postIDName of ['spid', 'pid', 'tid']) { // todo: sub posts id goes first to simply verbose multi post id condition
                                let postIDParam = _.filter(params, (param) => param.name === postIDName);
                                let isOnlyOnePostIDParam = _.isEmpty(_.filter(params, (param) => param.name !== postIDName)) && postIDParam.length === 1 && postIDParam[0].subParam == null;
                                if (isOnlyOnePostIDParam) {
                                    this.$router.push({ name: `${postIDName}${newRouteWithPage.name}`, params: { [postIDName]: postIDParam[0].value, ...newRouteWithPage.params } });
                                    return; // exit early to prevent parse other post id params
                                }
                            }
                        }
                        if (_.isEmpty(params) && _.isEmpty(_.omit(uniqueParams, 'fid', 'page'))) { // fid route
                            this.$router.push({ name: `fid${newRouteWithPage.name}`, params: { fid: uniqueParams.fid.value, ...newRouteWithPage.params } });
                            return;
                        }
                        this.$router.push({ path: `/${_.chain([..._.values(uniqueParams), ...params]) // param route
                            .map((param) => `${param.name}:${this.escapeParamValue(_.isArray(param.value) ? param.value.join(',') : param.value)}${_.map(param.subParam, (value, name) => `;${name}:${this.escapeParamValue(value)}`).join('')}`)  // format param to route path, e.g. name:value;subParamName:subParamValue...
                            .join('/')
                            .value()}` });
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
                    }
                }
            });

            const postsQueryComponent = Vue.component('posts-query', {
                template: '#posts-query-template',
                props: {
                },
                data () {
                    return {
                        forumList: []
                    };
                },
                computed: {
                },
                created () {
                    $$loadForumList().then((forumList) => this.$data.forumList = forumList);
                },
                mounted () {

                },
                methods: {
                },
                beforeRouteUpdate (to, from, next) {
                    console.log(to, from);
                    const flatParam = (param) => {
                        let flatted = {};
                        flatted[param.name] = param.value;
                        return _.merge(flatted, param.subParam);
                    };
                    console.log(JSON.stringify([
                        ..._.chain(this.$refs.queryForm.uniqueParams).map(this.$refs.queryForm.clearParamDefaultValue).filter().map(flatParam).values().value(),
                        ..._.chain(this.$refs.queryForm.params).map(this.$refs.queryForm.clearParamDefaultValue).filter().map(flatParam).value()
                    ]));
                    next();
                }
            });

            const postsQueryVue = new Vue({
                el: '#posts-query',
                data () {
                    return {
                    };
                },
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
                                { name: 'param', path: '*' },
                            ]
                        }
                    ]
                })
            });
        </script>
    @endverbatim
@endsection
