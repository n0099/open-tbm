<template>
    <form @submit.prevent="submit()" class="query-form mt-3">
        <div class="form-group form-row">
            <label class="col-1 col-form-label" for="paramFid">贴吧</label>
            <div class="col-3 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><FontAwesomeIcon icon="filter" /></span>
                </div>
                <select v-model.number="uniqueParams.fid.value" :class="{ 'is-invalid': isFidInvalid }"
                        id="paramFid" class="custom-select form-control">
                    <option value="NULL">未指定</option>
                    <option v-for="forum in forumList" :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                </select>
            </div>
            <label class="col-1 col-form-label text-center">贴子类型</label>
            <div class="input-group my-auto col">
                <div class="custom-checkbox custom-control custom-control-inline">
                    <input v-model="uniqueParams.postTypes.value" id="paramPostTypesThread"
                           type="checkbox" value="thread" class="custom-control-input">
                    <label class="custom-control-label" for="paramPostTypesThread">主题贴</label>
                </div>
                <div class="custom-checkbox custom-control custom-control-inline">
                    <input v-model="uniqueParams.postTypes.value" id="paramPostTypesReply"
                           type="checkbox" value="reply" class="custom-control-input">
                    <label class="custom-control-label" for="paramPostTypesReply">回复贴</label>
                </div>
                <div class="custom-checkbox custom-control custom-control-inline">
                    <input v-model="uniqueParams.postTypes.value" id="paramPostTypesSubReply"
                           type="checkbox" value="subReply" class="custom-control-input">
                    <label class="custom-control-label" for="paramPostTypesSubReply">楼中楼</label>
                </div>
            </div>
        </div>
        <div class="form-group form-row">
            <label class="col-1 col-form-label" for="paramOrder">排序方式</label>
            <div id="paramOrder" class="col-8 input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><FontAwesomeIcon icon="sort-amount-down" /></span>
                </div>
                <select v-model="uniqueParams.orderBy.value" :class="{ 'is-invalid': isOrderByInvalid }"
                        class="col custom-select form-control">
                    <option value="default">默认（按贴索引查询按贴子ID正序，按吧索引/搜索查询按发帖时间倒序）</option>
                    <option value="postTime">发帖时间</option>
                    <optgroup label="贴子ID">
                        <option value="tid">主题贴tid</option>
                        <option value="pid">回复贴pid</option>
                        <option value="spid">楼中楼spid</option>
                    </optgroup>
                </select>
                <select v-show="uniqueParams.orderBy.value !== 'default'" v-model="uniqueParams.orderBy.subParam.direction"
                        class="col-6 custom-select form-control">
                    <option value="ASC">正序（从小到大，旧到新）</option>
                    <option value="DESC">倒序（从大到小，新到旧）</option>
                </select>
            </div>
        </div>
        <div v-for="(param, paramIndex) in params" :key="paramIndex" class="query-param-row form-row">
            <div class="input-group">
                <button @click="deleteParam(paramIndex)" class="btn btn-link" type="button"><FontAwesomeIcon icon="times" /></button>
                <SelectParam @paramChange="changeParam(paramIndex, $event.value)" :currentParam="param.name" :class="{
                    'is-invalid': invalidParamsIndex.includes(paramIndex),
                    'select-param-first-row': paramIndex === 0,
                    'select-param-last-row': paramIndex === params.length - 1,
                    'select-param': true
                }" />
                <div class="input-group-prepend input-group-append">
                    <div class="param-input-group-text input-group-text">
                        <div class="custom-checkbox custom-control">
                            <input v-model="param.subParam.not" :id="`param${_.upperFirst(param.name)}Not-${paramIndex}`"
                                   type="checkbox" value="good" class="custom-control-input">
                            <label :for="`param${_.upperFirst(param.name)}Not-${paramIndex}`"
                                   class="text-secondary font-weight-bold custom-control-label">非</label>
                        </div>
                    </div>
                </div>
                <template v-if="_.includes(['tid', 'pid', 'spid'], param.name)">
                    <SelectRange v-model="param.subParam.range" />
                    <InputNumericParam v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)" :placeholders="{
                        IN: param.name === 'tid' ? '5000000000,5000000001,5000000002,...' : '15000000000,15000000001,15000000002,...',
                        BETWEEN: param.name === 'tid' ? '5000000000,6000000000' : '15000000000,16000000000',
                        number: param.name === 'tid' ? 5000000000 : 15000000000
                    }" />
                </template>
                <template v-if="_.includes(['postTime', 'latestReplyTime'], param.name)">
                    <RangePicker v-model="param.subParam.range" :show-time="true"
                                 format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DDTHH:mm" size="large" class="a-datetime-range" />
                </template>
                <template v-if="_.includes(['threadTitle', 'postContent', 'authorName', 'authorDisplayName', 'latestReplierName', 'latestReplierDisplayName'], param.name)">
                    <input v-model="param.value" :placeholder="param.subParam.matchBy === 'implicit' ? '模糊' : (param.subParam.matchBy === 'explicit' ? '精确' : '正则') + `匹配 空格${param.subParam.spaceSplit ? '不' : ''}分割关键词`" type="text" class="form-control" required>
                    <InputTextMatchParam v-model="params[paramIndex]" :param-index="paramIndex" :classes="paramRowLastDomClass(paramIndex, params)" />
                </template>
                <template v-if="_.includes(['threadViewNum', 'threadShareNum', 'threadReplyNum', 'replySubReplyNum'], param.name)">
                    <SelectRange v-model="param.subParam.range" />
                    <InputTextMatchParam v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)"
                                         :placeholders="{ IN: '100,101,102,...', BETWEEN: '100,200', number: 100 }" />
                </template>
                <template v-if="param.name === 'threadProperties'">
                    <div class="input-group-append">
                        <div class="param-input-group-text input-group-text">
                            <div class="custom-checkbox custom-control">
                                <input v-model="param.value" :id="`paramThreadPropertiesGood-${paramIndex}`"
                                       type="checkbox" value="good" class="custom-control-input">
                                <label :for="`paramThreadPropertiesGood-${paramIndex}`"
                                       class="text-danger font-weight-normal custom-control-label">精品</label>
                            </div>
                        </div>
                    </div>
                    <div class="input-group-append">
                        <div :class="paramRowLastDomClass(paramIndex, params)" class="param-input-group-text input-group-text">
                            <div class="custom-checkbox custom-control">
                                <input v-model="param.value" :id="`paramThreadPropertiesSticky-${paramIndex}`"
                                       type="checkbox" value="sticky" class="custom-control-input">
                                <label :for="`paramThreadPropertiesSticky-${paramIndex}`"
                                       class="text-primary font-weight-normal custom-control-label">置顶</label>
                            </div>
                        </div>
                    </div>
                </template>
                <template v-if="_.includes(['authorUid', 'latestReplierUid'], param.name)">
                    <SelectRange v-model="param.subParam.range"></SelectRange>
                    <InputNumericParam v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)" :placeholders="{
                        IN: '4000000000,4000000001,4000000002,...',
                        BETWEEN: '4000000000,5000000000',
                        number: 4000000000
                    }" />
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
                    <SelectRange v-model="param.subParam.range" />
                    <InputNumericParam v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)"
                                       :placeholders="{ IN: '9,10,11,...', BETWEEN: '9,18', number: 18 }" />
                </template>
            </div>
        </div>
        <div class="mt-1 form-group form-row">
            <button class="add-param-button disabled btn btn-link" type="button"><FontAwesomeIcon icon="plus" /></button>
            <SelectParam @paramChange="addParam($event)" />
        </div>
        <div class="form-group form-row">
            <button :disabled="isLoading" class="btn btn-primary" type="submit">
                查询 <span v-show="isLoading" class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
            </button>
            <button class="ml-2 disabled btn btn-text" type="button">
                {{ currentQueryType() === 'fid' ? '按吧索引查询' : (currentQueryType() === 'postID' ? '按贴索引查询' : (currentQueryType() === 'search' ? '搜索查询' : '空查询')) }}
            </button>
        </div>
    </form>
</template>

<script lang="ts">
import type { ApiForumList } from '@/api/index.d';
import type { ParamOmitName, ParamPreprocessorOrWatcher } from '@/components/Post/useQueryForm';
import useQueryForm from '@/components/Post/useQueryForm';
import { routeNameStrAssert } from '@/shared';
import { InputNumericParam, InputTextMatchParam, SelectParam, SelectRange } from './';

import type { PropType } from 'vue';
import { defineComponent, reactive, toRefs } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { useRouter } from 'vue-router';
import { RangePicker } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';
import Noty from 'noty';

export default defineComponent({
    components: { FontAwesomeIcon, RangePicker, InputNumericParam, InputTextMatchParam, SelectParam, SelectRange },
    props: {
        forumList: { type: Array as PropType<ApiForumList>, required: true }
    },
    setup(props, { emit }) {
        const router = useRouter();
        type RequiredPostTypes = Record<string, [Array<'reply' | 'subReply' | 'thread'>, 'AND' | 'OR'] | undefined>;
        const state = reactive<{
            paramsRequiredPostTypes: RequiredPostTypes,
            orderByRequiredPostTypes: RequiredPostTypes,
            isOrderByInvalid: boolean,
            isFidInvalid: boolean
        }>({
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
            orderByRequiredPostTypes: {
                pid: [['reply', 'subReply'], 'OR'],
                spid: [['subReply'], 'OR']
            },
            isOrderByInvalid: false,
            isFidInvalid: false
        });
        let useQueryFormLateBinding = {};
        const {
            state: useState,
            paramRowLastDomClass,
            escapeParamValue,
            addParam,
            changeParam,
            deleteParam,
            fillParamWithDefaultValue,
            clearParamDefaultValue,
            clearedParamsDefaultValue,
            clearedUniqueParamsDefaultValue,
            parseParamRoute,
            submitParamRoute,
            submit
        } = useQueryForm(emit, useQueryFormLateBinding);
        const paramTypes: Record<string, {
            default?: ParamOmitName,
            preprocessor?: ParamPreprocessorOrWatcher,
            watcher?: ParamPreprocessorOrWatcher
        }> = { // param is byref object so changes will sync
            array: {
                preprocessor: param => {
                    if (_.isString(param.value)) param.value = param.value.split(',');
                }
            },
            numeric: { default: { subParam: { range: '=' } } },
            textMatch: {
                default: { subParam: { matchBy: 'implicit', spaceSplit: false } },
                preprocessor: param => {
                    param.subParam.spaceSplit = param.subParam.spaceSplit === 'true'; // literal string to bool convert
                },
                watcher: param => {
                    if (param.subParam?.matchBy === 'regex') param.subParam.spaceSplit = false;
                }
            },
            dateTimeRange: {
                preprocessor: param => {
                    if (param.subParam === undefined || !_.isString(param.value)) return;
                    param.subParam.range = param.value.split(',');
                },
                watcher: param => {
                    // combine datetime range into root param's value
                    param.value = _.isArray(param.subParam?.range) ? param.subParam?.range.join(',') : '';
                }
            }
        };
        useState.uniqueParams = {
            fid: { name: 'fid' },
            postTypes: { name: 'postTypes' },
            orderBy: { name: 'orderBy' }
        };
        useState.paramsDefaultValue = {
            fid: { value: 'NULL' },
            postTypes: { value: ['thread', 'reply', 'subReply'] },
            orderBy: { value: 'default', subParam: { direction: 'default' } },
            tid: paramTypes.numeric.default,
            pid: paramTypes.numeric.default,
            spid: paramTypes.numeric.default,
            postTime: { subParam: { range: undefined } },
            latestReplyTime: { subParam: { range: undefined } },
            threadTitle: paramTypes.textMatch.default,
            postContent: paramTypes.textMatch.default,
            threadViewNum: paramTypes.numeric.default,
            threadShareNum: paramTypes.numeric.default,
            threadReplyNum: paramTypes.numeric.default,
            replySubReplyNum: paramTypes.numeric.default,
            threadProperties: { value: [] },
            authorUid: paramTypes.numeric.default,
            authorName: paramTypes.textMatch.default,
            authorDisplayName: paramTypes.textMatch.default,
            authorExpGrade: paramTypes.numeric.default,
            latestReplierUid: paramTypes.numeric.default,
            latestReplierName: paramTypes.textMatch.default,
            latestReplierDisplayName: paramTypes.textMatch.default
        };
        useState.paramsPreprocessor = {
            postTypes: paramTypes.array.preprocessor,
            postTime: paramTypes.dateTimeRange.preprocessor,
            latestReplyTime: paramTypes.dateTimeRange.preprocessor,
            threadTitle: paramTypes.textMatch.preprocessor,
            postContent: paramTypes.textMatch.preprocessor,
            threadProperties: paramTypes.array.preprocessor,
            authorName: paramTypes.textMatch.preprocessor,
            authorDisplayName: paramTypes.textMatch.preprocessor,
            latestReplierName: paramTypes.textMatch.preprocessor,
            latestReplierDisplayName: paramTypes.textMatch.preprocessor
        };
        useState.paramsWatcher = {
            postTime: paramTypes.dateTimeRange.watcher,
            latestReplyTime: paramTypes.dateTimeRange.watcher,
            threadTitle: paramTypes.textMatch.watcher,
            postContent: paramTypes.textMatch.watcher,
            authorName: paramTypes.textMatch.watcher,
            authorDisplayName: paramTypes.textMatch.watcher,
            latestReplierName: paramTypes.textMatch.watcher,
            latestReplierDisplayName: paramTypes.textMatch.watcher,
            orderBy(param) {
                if (param.value === 'default') { // reset to default
                    param.subParam = { ...param.subParam, direction: 'default' };
                }
            }
        };
        const currentQueryType = () => {
            const clearedParams = clearedParamsDefaultValue();
            if (_.isEmpty(clearedParams)) { // is there no other params
                const clearedUniqueParams = clearedUniqueParamsDefaultValue('postTypes', 'orderBy');
                if (_.isEmpty(clearedUniqueParams)) { // only fill unique param postTypes and/or orderBy doesn't query anything
                    return 'empty';
                } else if (clearedUniqueParams.fid !== undefined) {
                    return 'fid'; // note when query with postTypes and/or orderBy param, the route will goto params not the fid
                }
            }
            if (_.isEmpty(_.reject(clearedParams, param => _.includes(['tid', 'pid', 'spid'], param.name))) // is there no other params
                && _.filter(clearedParams, param => _.includes(['tid', 'pid', 'spid'], param.name)).length === 1 // is there only one post id param
                && _.isEmpty(_.filter(_.map(clearedParams, 'subParam')))) { // is post id param haven't any sub param
                return 'postID';
            }
            return 'search';
        };
        const parseRoute = (route: RouteLocationNormalizedLoaded) => {
            routeNameStrAssert(route.name);
            useState.uniqueParams = _.mapValues(useState.uniqueParams, _.unary(fillParamWithDefaultValue));
            useState.params = [];
            // parse route path to params
            if (route.name.startsWith('param')) {
                parseParamRoute(route.params.pathMatch); // omit page param from route full path
            } else if (route.name.startsWith('fid')) {
                useState.uniqueParams.fid.value = route.params.fid;
            } else { // post id routes
                useState.uniqueParams = _.mapValues(useState.uniqueParams, param =>
                    fillParamWithDefaultValue(param, true)); // reset to default
                // eslint-disable-next-line @typescript-eslint/no-shadow
                useState.params = _.map(_.omit(route.params, 'pathMatch', 'page'), (value, name) =>
                    fillParamWithDefaultValue({ name, value }));
            }
        };
        const checkParams = () => {
            // check query type
            state.isFidInvalid = false;
            const clearedUniqueParams = clearedUniqueParamsDefaultValue();
            switch (currentQueryType()) {
                case 'empty':
                    new Noty({ timeout: 3000, type: 'warning', text: '请选择贴吧或/并输入查询参数，勿只选择贴子类型参数' }).show();
                    return false; // exit early
                case 'postID':
                    if (clearedUniqueParams.fid !== undefined) {
                        useState.uniqueParams.fid.value = state.fid.value; // reset fid to default value.default,
                        new Noty({ timeout: 3000, type: 'info', text: '已移除按贴索引查询所不需要的查询贴吧参数' }).show();
                        submitRoute(); // update route to match new params without fid
                    }
                    return true; // index query doesn't restrict on post types
                case 'search':
                    if (clearedUniqueParams.fid === undefined) {
                        state.isFidInvalid = true; // search query require fid param
                        new Noty({ timeout: 3000, type: 'warning', text: '搜索查询必须指定查询贴吧' }).show();
                    }
                    break;
                case 'fid':
            }
            // check params required post types
            const postTypes = _.sortBy(useState.uniqueParams.postTypes.value);
            useState.invalidParamsIndex = []; // reset to prevent duplicate indexes
            _.each(_.map(useState.params, clearParamDefaultValue), (param, paramIndex) => { // we don't filter() here for post types validate
                if (param?.value === undefined) {
                    useState.invalidParamsIndex.push(paramIndex);
                } else {
                    const paramRequiredPostTypes = state.paramsRequiredPostTypes[param.name];
                    if (paramRequiredPostTypes !== undefined// not set means this param accepts any post types
                        && !(paramRequiredPostTypes[1] === 'OR' // does uniqueParams.postTypes fits with params required post types
                            ? _.isEmpty(_.difference(postTypes, _.sortBy(paramRequiredPostTypes[0])))
                            : _.isEqual(_.sortBy(paramRequiredPostTypes[0]), postTypes))) {
                        // is this param have no diff with default value and have value
                        useState.invalidParamsIndex.push(paramIndex);
                    }
                }
            });
            if (!_.isEmpty(useState.invalidParamsIndex)) new Noty({ timeout: 3000, type: 'warning', text: `第${_.map(useState.invalidParamsIndex, i => i + 1).join(',')}项查询参数与查询贴子类型要求不匹配` }).show();

            // check order by required post types
            state.isOrderByInvalid = false;
            const orderBy = useState.uniqueParams.orderBy.value;
            if (orderBy in state.orderByRequiredPostTypes) {
                const orderByRequiredPostTypes = state.orderByRequiredPostTypes[orderBy];
                if (!(orderByRequiredPostTypes[1] === 'OR'
                    ? _.isEmpty(_.difference(postTypes, _.sortBy(orderByRequiredPostTypes[0])))
                    : _.isEqual(_.sortBy(orderByRequiredPostTypes[0]), postTypes))) {
                    state.isOrderByInvalid = true;
                    new Noty({ timeout: 3000, type: 'warning', text: '排序方式与查询贴子类型要求不匹配' }).show();
                }
            }

            return _.isEmpty(useState.invalidParamsIndex) && !state.isOrderByInvalid && !state.isFidInvalid; // return false when there's any invalid params
        };
        const submitRoute = () => {
            // decide which route to go
            const clearedParams = clearedParamsDefaultValue();
            const clearedUniqueParams = clearedUniqueParamsDefaultValue();
            if (_.isEmpty(clearedUniqueParams)) { // might be post id route
                for (const postIDName of ['spid', 'pid', 'tid']) { // todo: sub posts id goes first to simply verbose multi post id condition
                    const postIDParam = _.filter(clearedParams, param => param.name === postIDName);
                    if (_.isEmpty(_.reject(clearedParams, param => param.name === postIDName)) // is there no other params
                        && postIDParam.length === 1 // is there only one post id param
                        && postIDParam[0].subParam === undefined) { // is range subParam not set
                        router.push({ name: postIDName, params: { [postIDName]: postIDParam[0].value } });
                        return; // exit early to prevent pushing other route
                    }
                }
            }
            if (clearedUniqueParams.fid !== undefined
                && _.isEmpty(clearedParams)
                && _.isEmpty(_.omit(clearedUniqueParams, 'fid'))) { // fid route
                router.push({ name: 'fid', params: { fid: clearedUniqueParams.fid.value } });
                return;
            }
            submitParamRoute(clearedUniqueParams, clearedParams); // param route
        };
        useQueryFormLateBinding = { parseRoute, checkParams, submitRoute };

        return { _, ...toRefs(state), ...toRefs(useState), currentQueryType, paramRowLastDomClass, escapeParamValue, addParam, changeParam, deleteParam, submit };
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
