@extends('layout')
@include('module.bootstrapCallout')
@include('module.tiebaPostContentElement')
@include('module.vue.scrollList')
@include('module.vue.tiebaSelectUser')

@section('title', '贴子查询')

@section('style')
    <style>
        .query-param-row {
            margin-top: -1px;
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
    </style>
@endsection

@section('container')
    @verbatim
        <template id="select-param-template">
            <select @change="$emit('param-change', $event)" class="col-2 form-control">
                <option selected="selected" value="add" disabled>New...</option>
                <optgroup v-for="(group, groupName) in paramsGroup" :label="groupName">
                    <option v-for="param in group" :selected="currentParam === param.name" :value="param.name" v-text="param.description"></option>
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
                        <select v-model="uniqueParams.fid.value" id="paramFid" class="form-control">
                            <option value="NULL">未指定</option>
                            <option v-for="forum in forumList" :key="forum.fid" :value="forum.fid" v-text="forum.name"></option>
                        </select>
                    </div>
                    <label class="text-center col-1 col-form-label">贴子类型</label>
                    <div class="input-group my-auto col">
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypeThread" type="checkbox" value="thread" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypeThread">主题贴</label>
                        </div>
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypeReply" type="checkbox" value="reply" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypeReply">回复贴</label>
                        </div>
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypeSubReply" type="checkbox" value="subReply" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypeSubReply">楼中楼</label>
                        </div>
                    </div>
                </div>
                <div class="form-group form-row">
                    <label class="col-1 col-form-label" for="paramOrder">排序方式</label>
                    <div id="paramOrder" class="col-8 input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-sort-amount-down"></i></span>
                        </div>
                        <select v-model="uniqueParams.orderBy.value" class="col form-control">
                            <option value="default">默认（按贴索引查询按发贴时间正序，按吧索引/搜索查询倒序）</option>
                            <option value="postTime">发贴时间</option>
                            <optgroup label="贴子ID">
                                <option value="tid">主题贴tid</option>
                                <option value="pid">回复贴pid</option>
                                <option value="spid">楼中楼spid</option>
                            </optgroup>
                        </select>
                        <select v-if="uniqueParams.orderBy.value !== 'default'" v-model="uniqueParams.orderBy.subParam.direction" class="col-6 form-control">
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
                                'select-param-last-row': paramIndex === params.length - 1
                            }"></select-param>
                        <template v-if="param.name === 'tid'">
                            <select v-model="param.subParam.range" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                                <option>IN</option>
                                <option>BETWEEN</option>
                            </select>
                            <input v-if="param.subParam.range === 'IN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="text" class="col form-control" placeholder="5000000000,5000000001,5000000002,..." aria-label="tid" required pattern="\d+(,\d+)+" />
                            <input v-else-if="param.subParam.range === 'BETWEEN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="text" class="col-3 form-control" placeholder="5000000000,6000000000" aria-label="tid" required pattern="\d+,\d+" />
                            <input v-else v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="number" class="col-2 form-control" placeholder="5000000000" aria-label="tid" required />
                        </template>
                        <template v-if="param.name === 'pid'">
                            <select v-model="param.subParam.range" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                                <option>IN</option>
                                <option>BETWEEN</option>
                            </select>
                            <input v-if="param.subParam.range === 'IN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="text" class="col form-control" placeholder="15000000000,15000000001,15000000002,..." aria-label="pid" required pattern="\d+(,\d+)+" />
                            <input v-else-if="param.subParam.range === 'BETWEEN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="text" class="col-3 form-control" placeholder="15000000000,16000000000" aria-label="pid" required pattern="\d+,\d+" />
                            <input v-else v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="number" class="col-2 form-control" placeholder="15000000000" aria-label="pid" required />
                        </template>
                        <template v-if="param.name === 'spid'">
                            <select v-model="param.subParam.range" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                                <option>IN</option>
                                <option>BETWEEN</option>
                            </select>
                            <input v-if="param.subParam.range === 'IN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="text" class="col form-control" placeholder="15000000000,15000000001,15000000002,..." aria-label="spid" required pattern="\d+(,\d+)+" />
                            <input v-else-if="param.subParam.range === 'BETWEEN'" v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="text" class="col-3 form-control" placeholder="15000000000,16000000000" aria-label="spid" required pattern="\d+,\d+" />
                            <input v-else v-model="param.value" :class="getControlRowClass(paramIndex, params)"
                                   type="number" class="col-2 form-control" placeholder="15000000000" aria-label="spid" required />
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
                    <label class="col-2 col-form-label" v-text="`route page: ${$route.params.page}`"></label>
                </div>
            </form>
        </template>
        <template id="posts-query-template">
            <query-form :forum-list="forumList"></query-form>
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

            const selectParamComponent = Vue.component('select-param', {
                template: '#select-param-template',
                props: {
                    currentParam: String
                },
                data () {
                    return {
                        paramsGroup: {
                            ID: [
                                { name: 'tid', description: 'tid（主题贴ID）' },
                                { name: 'pid', description: 'pid（回复贴ID）' },
                                { name: 'spid', description: 'spid（楼中楼ID）' }
                            ]
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
                        queryData: { query:{}, params:{} },
                        uniqueParams: {
                            fid: { name: 'fid' },
                            postTypes: { name: 'postTypes' },
                            orderBy: { name: 'orderBy' },
                            page: { name: 'page' }
                        },
                        params: [], // [{ name: '', value: '', subParam: { name: value } }, ...]
                        paramsDefaultValue: {
                            fid: { value: 'NULL' },
                            postTypes: { value: _.sortBy(['thread', 'reply', 'subReply']) }, // sort here to prevent further sort while comparing
                            orderBy: { value: 'default', subParam: { direction: 'default' } },
                            page: { value: 1 },
                            // up above are unique params
                            tid: { subParam: { range: '=' } },
                            pid: { subParam: { range: '=' } },
                            spid: { subParam: { range: '=' } },
                            threadProperties: { value: [] },
                            userManagerTypes: { value: 'default' },
                            userGender: { value: 'default' },
                            userExpGrade: { subParam: { range: '=' } },
                            threadReplyNum: { subParam: { range: '=' } },
                            replySubReplyNum: { subParam: { range: '=' } },
                            threadViewNum: { subParam: { range: '=' } },
                            threadShareNum: { subParam: { range: '=' } }
                        },
                        invalidParamsIndex: []
                    };
                },
                computed: {
                },
                watch: {
                    'uniqueParams.orderBy.value': function (orderBy) {
                        if (orderBy === 'default') { // reset to default
                            this.$data.uniqueParams.orderBy.subParam.direction = 'default';
                        }
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
                            .filter() // filter will remove falsy values like ''
                            .map((paramWithSub) => {
                                let parsedParam = { subParam: {} };
                                _.map(paramWithSub.split(';'), (params, paramIndex) => {
                                    let paramPair = params.split(':');
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
                                if (_.includes(_.keys(this.$data.uniqueParams), param.name)) { // is unique param
                                    if (param.name === 'postTypes') { // array type param
                                        param.value = param.value.split(',');
                                    }
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
                        if (_.isArray(param.value) ? _.isEqual(_.sortBy(param.value), defaultParam.value) : param.value === defaultParam.value) {
                            delete param.value;
                        }
                        _.each(defaultParam.subParam, (value, name) => {
                            if (param.subParam[name] === value) {
                                delete param.subParam[name];
                            }
                        });
                        if (_.isEmpty(param.subParam)) {
                            delete param.subParam;
                        }
                        return _.isEqual(_.keys(param), ['name']) ? null : param;  // return null for further filter()
                    },
                    formatParamToPath (param) { // name:value;subParamName:subParamValue...
                        return `${param.name}:${this.escapeParamValue(_.isArray(param.value) ? param.value.join(',') : param.value)}${_.map(param.subParam, (value, name) => `;${name}:${this.escapeParamValue(value)}`).join('')}`;
                    },
                    formatCurrentParamsToPath () {
                        this.$data.invalidParamsIndex = []; // reset to prevent duplicate indexes
                        return _.chain({ uniqueParams: _.values(this.$data.uniqueParams), params: this.$data.params })
                            .map((params, paramsType) =>
                                _.map(params, (param, paramIndex) => {
                                    let clearedParam = this.clearParamDefaultValue(param);
                                    if (clearedParam === null && paramsType === 'params') {
                                        this.$data.invalidParamsIndex.push(paramIndex);
                                    }
                                    return clearedParam;
                                })
                            )
                            .flatten()
                            .filter() // filter will remove falsy values like null
                            .map(this.formatParamToPath)
                            .join('/')
                            .value();
                    },
                    submit () {
                        let newRouteWithPage = {
                            name: this.$data.uniqueParams.page.value !== 1 ? '+p' : '',
                            params: { page: this.$data.uniqueParams.page.value }
                        };
                        // filter will remove falsy values like null
                        let params = _.filter(_.map(this.$data.params, this.clearParamDefaultValue));
                        let uniqueParams = _.pickBy(_.mapValues(this.$data.uniqueParams, this.clearParamDefaultValue)); // remain keys, pickBy() like filter() for objects
                        // return _.isEmpty(_.filter(this.$data.params, (param) => ! _.includes(['tid', 'pid', 'spid'], param.name)));
                        // isParamsIndexQuery() ? (_.isEmpty(params) ? ( uniqueParams.fid.value === 'NULL' ? '全局索引查询' : '按吧索引查询') : '按贴索引查询') : '搜索查询'
                        //let isOnlyPostsID = _.isEmpty(_.omit(params, 'tid', 'pid', 'spid'))
                        //    && params.tid.subParam === null && params.pid.subParam === null && params.spid.subParam === null; // there's no range sub param
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
                        this.$router.push({ path: `/${this.formatCurrentParamsToPath()}` }); // param route
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
                    $$loadForumList().then((forumList) => {
                        this.$data.forumList = forumList;
                    });
                },
                mounted () {

                },
                methods: {
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
