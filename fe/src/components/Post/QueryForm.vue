<template>
    <form @submit.prevent="submit()" class="query-form mt-3">
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
                    <option value="default">默认（按贴索引查询按贴子ID正序，按吧索引/搜索查询按发帖时间倒序）</option>
                    <option value="postTime">发帖时间</option>
                    <optgroup label="贴子ID">
                        <option value="tid">主题贴tid</option>
                        <option value="pid">回复贴pid</option>
                        <option value="spid">楼中楼spid</option>
                    </optgroup>
                </select>
                <select v-show="uniqueParams.orderBy.value !== 'default'" v-model="uniqueParams.orderBy.subParam.direction" class="col-6 custom-select form-control">
                    <option value="ASC">正序（从小到大，旧到新）</option>
                    <option value="DESC">倒序（从大到小，新到旧）</option>
                </select>
            </div>
        </div>
        <div v-for="(param, paramIndex) in params" class="query-param-row form-row">
            <div class="input-group">
                <button @click="deleteParam(paramIndex)" class="btn btn-link" type="button"><i class="fas fa-times"></i></button>
                <select-param @param-change="changeParam(paramIndex, $event.target.value)" :current-param="param.name"
                              :class="{
                                    'is-invalid': invalidParamsIndex.includes(paramIndex),
                                    'select-param-first-row': paramIndex === 0,
                                    'select-param-last-row': paramIndex === params.length - 1,
                                    'select-param': true
                                  }"></select-param>
                <div class="input-group-prepend input-group-append">
                    <div class="param-input-group-text input-group-text">
                        <div class="custom-checkbox custom-control">
                            <input v-model="param.subParam.not" :id="`param${_.upperFirst(param.name)}Not-${paramIndex}`" type="checkbox" value="good" class="custom-control-input">
                            <label :for="`param${_.upperFirst(param.name)}Not-${paramIndex}`" class="text-secondary font-weight-bold custom-control-label">非</label>
                        </div>
                    </div>
                </div>
                <template v-if="_.includes(['tid', 'pid', 'spid'], param.name)">
                    <select-range v-model="param.subParam.range"></select-range>
                    <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)"
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
                    <input-text-match-param v-model="params[paramIndex]" :param-index="paramIndex" :classes="paramRowLastDomClass(paramIndex, params)"></input-text-match-param>
                </template>
                <template v-if="_.includes(['threadViewNum', 'threadShareNum', 'threadReplyNum', 'replySubReplyNum'], param.name)">
                    <select-range v-model="param.subParam.range"></select-range>
                    <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)" :placeholders="{ IN: '100,101,102,...', BETWEEN: '100,200', number: 100 }"></input-numeric-param>
                </template>
                <template v-if="param.name === 'threadProperties'">
                    <div class="input-group-append">
                        <div class="param-input-group-text input-group-text">
                            <div class="custom-checkbox custom-control">
                                <input v-model="param.value" :id="`paramThreadPropertiesGood-${paramIndex}`" type="checkbox" value="good" class="custom-control-input">
                                <label :for="`paramThreadPropertiesGood-${paramIndex}`" class="text-danger font-weight-normal custom-control-label">精品</label>
                            </div>
                        </div>
                    </div>
                    <div class="input-group-append">
                        <div :class="paramRowLastDomClass(paramIndex, params)" class="param-input-group-text input-group-text">
                            <div class="custom-checkbox custom-control">
                                <input v-model="param.value" :id="`paramThreadPropertiesSticky-${paramIndex}`" type="checkbox" value="sticky" class="custom-control-input">
                                <label :for="`paramThreadPropertiesSticky-${paramIndex}`" class="text-primary font-weight-normal custom-control-label">置顶</label>
                            </div>
                        </div>
                    </div>
                </template>
                <template v-if="_.includes(['authorUid', 'latestReplierUid'], param.name)">
                    <select-range v-model="param.subParam.range"></select-range>
                    <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)"
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
                    <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)" :placeholders="{ IN: '9,10,11,...', BETWEEN: '9,18', number: 18 }"></input-numeric-param>
                </template>
            </div>
        </div>
        <div class="mt-1 form-group form-row">
            <button class="add-param-button disabled btn btn-link" type="button"><i class="fas fa-plus"></i></button>
            <select-param @param-change="addParam($event)"></select-param>
        </div>
        <div class="form-group form-row">
            <button :disabled="isRequesting" class="btn btn-primary" type="submit">
                查询 <span v-show="isRequesting" class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
            </button>
            <button class="ml-2 disabled btn btn-text" type="button">{{ currentQueryType() === 'fid' ? '按吧索引查询' : (currentQueryType() === 'postID' ? '按贴索引查询' : (currentQueryType() === 'search' ? '搜索查询' : '空查询')) }}</button>
        </div>
    </form>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

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
            paramsDefaultValue: {
                // { name: *, subParam: { not: false } }
            },
            paramsPreprocessor: {},
            paramsWatcher: {},
            paramWatcher (newParamsArray, oldParamsArray) {
                _.chain(newParamsArray)
                    .filter((param) => _.includes(_.keys(this.$data.paramsWatcher), param.name))
                    .each((param) => this.$data.paramsWatcher[param.name](param))
                    .value();
            }
        };
    },
    watch: {
        uniqueParams: { handler: 'paramWatcher', deep: true },
        params: { handler: 'paramWatcher', deep: true },
        $route (to, from) {
            if (to.path === from.path) {
                return; // ignore when only hash has changed
            }
            if (! this.$data.isRequesting) { // isRequesting will be false when route change is not emit by <query-form>.submit()
                // these query logic is for route changes which is not trigger by <query-form>.submit(), such as user emitted history.back() or go()
                let isOnlyPageChanged = _.isEqual(_.omit(to.params, 'page'), _.omit(from.params, 'page'));
                this.parseRoute(to);
                if (isOnlyPageChanged || this.checkParams()) { // skip checkParams() when there's only page changed
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
                this.submitRoute();
                this.$emit('query', { queryParams: this.flattenParams(), shouldReplacePage: true }); // force emit event to refresh new query since route update event won't emit when isRequesting is true
            }
        },
        parseRoute (route) { throw('component must implements mixin abstract method'); },
        checkParams () { throw('component must implements mixin abstract method'); },
        submitRoute () { throw('component must implements mixin abstract method'); },
        paramRowLastDomClass (paramIndex, params) {
            return params.length === 1 ? {} : { // if it's the only row, class remains unchanged
                'param-control-first-row': paramIndex === 0,
                'param-control-middle-row': ! (paramIndex === 0 || paramIndex === params.length - 1),
                'param-control-last-row': paramIndex === params.length - 1
            };
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
            this.$data.invalidParamsIndex = _.map(this.$data.invalidParamsIndex, (invalidParamIndex) => invalidParamIndex > paramIndex ? invalidParamIndex - 1 : invalidParamIndex); // move forward params index which is after current one
            this.$delete(this.$data.params, paramIndex);
        },
        fillParamWithDefaultValue (param, resetToDefault = false) {
            let defaultParam = this.$data.paramsDefaultValue[param.name];
            defaultParam.subParam = defaultParam.subParam || {};
            defaultParam.subParam.not = false; // add default not subParam on every param
            if (resetToDefault) { // cloneDeep to prevent defaultsDeep mutates origin object
                return _.defaultsDeep(_.cloneDeep(defaultParam), param);
            } else {
                return _.defaultsDeep(_.cloneDeep(param), defaultParam);
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
            if (! (_.isNumber(param.value) || ! _.isEmpty(param.value)) // number is consider as empty in isEmpty(), to prevent this here we use complex short circuit evaluate expression
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
                    });
                    return parsedParam;
                })
                .map(_.unary(this.fillParamWithDefaultValue))
                .each((param) => {
                    if (_.includes(_.keys(this.$data.paramsPreprocessor), param.name)) {
                        this.$data.paramsPreprocessor[param.name](param);
                        param.subParam.not = param.subParam.not === 'true'; // literal string to bool convert
                    }
                    if (_.includes(_.keys(this.$data.uniqueParams), param.name)) { // is unique param
                        this.$data.uniqueParams[param.name] = param;
                    } else {
                        this.$data.params.push(param);
                    }
                })
                .value();
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
        forumList: { type: Array, required: true }
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
                textMatchParams: { subParam: { matchBy: 'implicit', spaceSplit: false } },
                fid: { value: 'NULL' },
                postTypes: { value: ['thread', 'reply', 'subReply'] },
                orderBy: { value: 'default', subParam: { direction: 'default' } },
                get tid () { return this.numericParams; },
                get pid () { return this.numericParams; },
                get spid () { return this.numericParams; },
                postTime: { subParam: { range: undefined } },
                latestReplyTime: { subParam: { range: undefined } },
                get threadTitle () { return this.textMatchParams; },
                get postContent () { return this.textMatchParams; },
                get threadViewNum () { return this.numericParams; },
                get threadShareNum () { return this.numericParams; },
                get threadReplyNum () { return this.numericParams; },
                get replySubReplyNum () { return this.numericParams; },
                threadProperties: { value: [] },
                get authorUid () { return this.numericParams; },
                get authorName () { return this.textMatchParams; },
                get authorDisplayName () { return this.textMatchParams; },
                get authorExpGrade () { return this.numericParams; },
                get latestReplierUid () { return this.numericParams; },
                get latestReplierName () { return this.textMatchParams; },
                get latestReplierDisplayName () { return this.textMatchParams; }
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
                get postTypes () { return this.arrayTypeParams; },
                get postTime () { return this.dateTimeRangeParams; },
                get latestReplyTime () { return this.dateTimeRangeParams; },
                get threadTitle () { return this.textMatchParams; },
                get postContent () { return this.textMatchParams; },
                get threadProperties () { return this.arrayTypeParams; },
                get authorName () { return this.textMatchParams; },
                get authorDisplayName () { return this.textMatchParams; },
                get latestReplierName () { return this.textMatchParams; },
                get latestReplierDisplayName () { return this.textMatchParams; }
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
                get postTime () { return this.dateTimeRangeParams; },
                get latestReplyTime ()  { return this.dateTimeRangeParams; },
                get threadTitle () { return this.textMatchParams; },
                get postContent () { return this.textMatchParams; },
                get authorName () { return this.textMatchParams; },
                get authorDisplayName () { return this.textMatchParams; },
                get latestReplierName () { return this.textMatchParams; },
                get latestReplierDisplayName () { return this.textMatchParams; },
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
                let clearedUniqueParams = this.clearedUniqueParamsDefaultValue('postTypes', 'orderBy');
                if (_.isEmpty(clearedUniqueParams)) { // only fill unique param postTypes and/or orderBy doesn't query anything
                    return 'empty';
                } else if (clearedUniqueParams.fid !== undefined) {
                    return 'fid'; // note when query with postTypes and/or orderBy param, the route will goto params not the fid
                }
            }
            if (_.isEmpty(_.reject(clearedParams, (param) => _.includes(['tid', 'pid', 'spid'], param.name))) // is there no other params
                && _.filter(clearedParams, (param) => _.includes(['tid', 'pid', 'spid'], param.name)).length === 1 // is there only one post id param
                && _.isEmpty(_.filter(_.map(clearedParams, 'subParam')))) { // is post id param haven't any sub param
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
                this.$data.uniqueParams = _.mapValues(this.$data.uniqueParams, (param) => this.fillParamWithDefaultValue(param, true)); // reset to default
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
                if (param !== null && param.value !== undefined) { // is param have no diff with default value and have value
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
                    if (_.isEmpty(_.reject(clearedParams, (param) => param.name === postIDName)) // is there no other params
                        && postIDParam.length === 1 // is there only one post id param
                        && postIDParam[0].subParam === undefined) { // is range subParam not set
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
</script>

<style scoped>
.query-form .a-datetime-range {
    margin-left: -1px;
}
.query-form .ant-calendar-picker-input {
    height: 38px;
    border-bottom-left-radius: 0;
    border-top-left-radius: 0;
}

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

.param-input-group-text {
    background-color: unset;
}

.add-param-button { /* fa-plus is wider than fa-times 3px */
    padding-left: 10px;
    padding-right: 11px;
}
</style>
