<template>
    <form @submit.prevent="submit()" class="mt-3">
        <div class="row">
            <label class="col-1 col-form-label" for="paramFid">贴吧</label>
            <div class="col-3">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="filter" /></span>
                    <select v-model.number="uniqueParams.fid.value" :class="{ 'is-invalid': isFidInvalid }"
                            id="paramFid" class="form-select form-control">
                        <option value="0">未指定</option>
                        <option v-for="forum in forumList" :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                    </select>
                </div>
            </div>
            <label class="col-1 col-form-label text-center">贴子类型</label>
            <div class="col my-auto">
                <div class="input-group">
                    <div class="form-check form-check-inline">
                        <input v-model="uniqueParams.postTypes.value" id="paramPostTypesThread"
                               type="checkbox" value="thread" class="form-check-input">
                        <label class="form-check-label" for="paramPostTypesThread">主题贴</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input v-model="uniqueParams.postTypes.value" id="paramPostTypesReply"
                               type="checkbox" value="reply" class="form-check-input">
                        <label class="form-check-label" for="paramPostTypesReply">回复贴</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input v-model="uniqueParams.postTypes.value" id="paramPostTypesSubReply"
                               type="checkbox" value="subReply" class="form-check-input">
                        <label class="form-check-label" for="paramPostTypesSubReply">楼中楼</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-2 mb-3">
            <label class="col-1 col-form-label" for="paramOrder">排序方式</label>
            <div id="paramOrder" class="col-8">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="sort-amount-down" /></span>
                    <select v-model="uniqueParams.orderBy.value" :class="{ 'is-invalid': isOrderByInvalid }"
                            class="form-select form-control">
                        <option value="default">默认（按贴索引查询按贴子ID正序，按吧索引/搜索查询按发帖时间倒序）</option>
                        <option value="postTime">发帖时间</option>
                        <optgroup label="贴子ID">
                            <option value="tid">主题贴tid</option>
                            <option value="pid">回复贴pid</option>
                            <option value="spid">楼中楼spid</option>
                        </optgroup>
                    </select>
                    <select v-show="uniqueParams.orderBy.value !== 'default'" v-model="uniqueParams.orderBy.subParam.direction"
                            class="form-select form-control">
                        <option value="ASC">正序（从小到大，旧到新）</option>
                        <option value="DESC">倒序（从大到小，新到旧）</option>
                    </select>
                </div>
            </div>
        </div>
        <div v-for="(param, paramIndex) in params" :key="paramIndex" class="query-param-row row">
            <div class="input-group">
                <button @click="deleteParam(paramIndex)" class="btn btn-link" type="button"><FontAwesomeIcon icon="times" /></button>
                <SelectParam @paramChange="changeParam(paramIndex, $event.value)" :currentParam="param.name" :class="{
                    'is-invalid': invalidParamsIndex.includes(paramIndex),
                    'select-param-first-row': paramIndex === 0,
                    'select-param-last-row': paramIndex === params.length - 1
                }" />
                <div class="param-input-group-text input-group-text">
                    <div class="form-check">
                        <input v-model="param.subParam.not" :id="`param${_.upperFirst(param.name)}Not-${paramIndex}`"
                               type="checkbox" value="good" class="form-check-input">
                        <label :for="`param${_.upperFirst(param.name)}Not-${paramIndex}`"
                               class="text-secondary fw-bold form-check-label">非</label>
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
                                 format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DDTHH:mm" size="large" />
                </template>
                <template v-if="_.includes(['threadTitle', 'postContent', 'authorName', 'authorDisplayName', 'latestReplierName', 'latestReplierDisplayName'], param.name)">
                    <input v-model="param.value" :placeholder="inputTextMatchParamPlaceholder(param)" type="text" class="form-control" required>
                    <InputTextMatchParam v-model="params[paramIndex]" :paramIndex="paramIndex" :classes="paramRowLastDomClass(paramIndex, params)" />
                </template>
                <template v-if="_.includes(['threadViewNum', 'threadShareNum', 'threadReplyNum', 'replySubReplyNum'], param.name)">
                    <SelectRange v-model="param.subParam.range" />
                    <InputNumericParam v-model="params[paramIndex]" :paramIndex="paramIndex"
                                       :classes="paramRowLastDomClass(paramIndex, params)"
                                       :placeholders="{ IN: '100,101,102,...', BETWEEN: '100,200', number: 100 }" />
                </template>
                <div v-if="param.name === 'threadProperties'" :class="paramRowLastDomClass(paramIndex, params)">
                    <div class="param-input-group-text input-group-text">
                        <div class="form-check">
                            <input v-model="param.value" :id="`paramThreadPropertiesGood-${paramIndex}`"
                                   type="checkbox" value="good" class="form-check-input">
                            <label :for="`paramThreadPropertiesGood-${paramIndex}`"
                                   class="text-danger fw-normal form-check-label">精品</label>
                        </div>
                    </div>
                    <div :class="paramRowLastDomClass(paramIndex, params)" class="param-input-group-text input-group-text">
                        <div class="form-check">
                            <input v-model="param.value" :id="`paramThreadPropertiesSticky-${paramIndex}`"
                                   type="checkbox" value="sticky" class="form-check-input">
                            <label :for="`paramThreadPropertiesSticky-${paramIndex}`"
                                   class="text-primary fw-normal form-check-label">置顶</label>
                        </div>
                    </div>
                </div>
                <template v-if="_.includes(['authorUid', 'latestReplierUid'], param.name)">
                    <SelectRange v-model="param.subParam.range"></SelectRange>
                    <InputNumericParam v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)" :placeholders="{
                        IN: '4000000000,4000000001,4000000002,...',
                        BETWEEN: '4000000000,5000000000',
                        number: 4000000000
                    }" />
                </template>
                <template v-if="param.name === 'authorManagerType'">
                    <select v-model="param.value" :class="paramRowLastDomClass(paramIndex, params)" class="form-control flex-grow-0 w-25">
                        <option value="NULL">吧友</option>
                        <option value="manager">吧主</option>
                        <option value="assist">小吧主</option>
                        <option value="voiceadmin">语音小编</option>
                    </select>
                </template>
                <template v-if="_.includes(['authorGender', 'latestReplierGender'], param.name)">
                    <select v-model="param.value" :class="paramRowLastDomClass(paramIndex, params)" class="form-control flex-grow-0 w-25">
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
        <div class="query-param-row row mt-2">
            <button class="add-param-button col-auto btn btn-link disabled" type="button"><FontAwesomeIcon icon="plus" /></button>
            <SelectParam @paramChange="addParam($event)" />
        </div>
        <div class="row mt-3">
            <button :disabled="isLoading" class="col-auto btn btn-primary" type="submit">
                查询 <span v-show="isLoading" class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
            </button>
            <span class="col-auto ms-3 my-auto text-muted">{{ currentQueryTypeDesc }}</span>
        </div>
    </form>
</template>

<script lang="ts">
import { InputNumericParam, InputTextMatchParam, SelectParam, SelectRange } from './';
import type { Params, UniqueParams, paramsNameByType } from './queryParams';
import {
    addParam,
    changeParam,
    clearParamDefaultValue,
    clearedParamsDefaultValue,
    clearedUniqueParamsDefaultValue,
    deleteParam,
    fillParamWithDefaultValue,
    orderByRequiredPostTypes,
    paramRowLastDomClass,
    paramsRequiredPostTypes,
    parseParamRoute,
    submitParamRoute,
    useQueryFormLateBinding,
    useState
} from './queryParams';
import { notyShow, routeNameStrAssert } from '@/shared';
import type { ApiForumList } from '@/api/index.d';

import type { PropType } from 'vue';
import { computed, defineComponent, reactive, toRefs } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { useRouter } from 'vue-router';
import { RangePicker } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';

export default defineComponent({
    components: { FontAwesomeIcon, RangePicker, InputNumericParam, InputTextMatchParam, SelectParam, SelectRange },
    props: {
        isLoading: { type: Boolean, required: true },
        forumList: { type: Array as PropType<ApiForumList>, required: true }
    },
    setup(props, { emit }) {
        const router = useRouter();
        const state = reactive<{
            isOrderByInvalid: boolean,
            isFidInvalid: boolean
        }>({
            isOrderByInvalid: false,
            isFidInvalid: false
        });

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
            useState.uniqueParams = _.mapValues(useState.uniqueParams, _.unary(fillParamWithDefaultValue)) as UniqueParams;
            useState.params = [];
            // parse route path to params
            if (route.name.startsWith('param') && !_.isArray(route.params.pathMatch)) {
                parseParamRoute(route.params.pathMatch); // omit page param from route full path
            } else if (route.name.startsWith('fid') && !_.isArray(route.params.fid)) {
                useState.uniqueParams.fid.value = parseInt(route.params.fid);
            } else { // post id routes
                useState.uniqueParams = _.mapValues(useState.uniqueParams, param =>
                    fillParamWithDefaultValue(param, true)) as UniqueParams; // reset to default
                // eslint-disable-next-line @typescript-eslint/no-shadow
                useState.params = _.map(_.omit(route.params, 'pathMatch', 'page'), (value, name) =>
                    fillParamWithDefaultValue({ name, value }));
            }
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
                        && postIDParam[0]?.subParam === undefined) { // is range subParam not set
                        router.push({ name: `post/${postIDName}`, params: { [postIDName]: postIDParam[0].value?.toString() } });
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
        const checkParams = () => {
            // check query type
            state.isFidInvalid = false;
            const clearedUniqueParams = clearedUniqueParamsDefaultValue();
            switch (currentQueryType()) {
                case 'empty':
                    notyShow('warning', '请选择贴吧或/并输入查询参数，勿只选择贴子类型参数');
                    return false; // exit early
                case 'postID':
                    if (clearedUniqueParams.fid !== undefined) {
                        useState.uniqueParams.fid.value = 0; // reset fid to default,
                        notyShow('info', '已移除按贴索引查询所不需要的查询贴吧参数');
                        submitRoute(); // update route to match new params without fid
                    }
                    return true; // index query doesn't restrict on post types
                case 'search':
                    if (clearedUniqueParams.fid === undefined) {
                        state.isFidInvalid = true; // search query require fid param
                        notyShow('warning', '搜索查询必须指定查询贴吧');
                    }
                    break;
                case 'fid':
            }
            // check params required post types
            const postTypes = _.sortBy(useState.uniqueParams.postTypes.value);
            useState.invalidParamsIndex = []; // reset to prevent duplicate indexes
            _.each(useState.params.map(clearParamDefaultValue), (param, paramIndex) => { // we don't filter() here for post types validate
                if (param === null || param.name === undefined || param.value === undefined) {
                    useState.invalidParamsIndex.push(paramIndex);
                } else {
                    const paramRequiredPostTypes = paramsRequiredPostTypes[param.name];
                    if (paramRequiredPostTypes !== undefined// not set means this param accepts any post types
                        && !(paramRequiredPostTypes[1] === 'OR' // does uniqueParams.postTypes fits with params required post types
                            ? _.isEmpty(_.difference(postTypes, _.sortBy(paramRequiredPostTypes[0])))
                            : _.isEqual(_.sortBy(paramRequiredPostTypes[0]), postTypes))) {
                        // is this param have no diff with default value and have value
                        useState.invalidParamsIndex.push(paramIndex);
                    }
                }
            });
            if (!_.isEmpty(useState.invalidParamsIndex)) {
                notyShow('warning',
                    `第${useState.invalidParamsIndex.map(i => i + 1).join(',')}项查询参数与查询贴子类型要求不匹配`);
            }
            // check order by required post types
            state.isOrderByInvalid = false;
            const orderBy = useState.uniqueParams.orderBy.value;
            if (orderBy in orderByRequiredPostTypes) {
                const requiredPostTypes = orderByRequiredPostTypes[orderBy];
                if (requiredPostTypes !== undefined && !(requiredPostTypes[1] === 'OR'
                    ? _.isEmpty(_.difference(postTypes, _.sortBy(requiredPostTypes[0])))
                    : _.isEqual(_.sortBy(requiredPostTypes[0]), postTypes))) {
                    state.isOrderByInvalid = true;
                    notyShow('warning', '排序方式与查询贴子类型要求不匹配');
                }
            }
            // return false when there's any invalid params
            return _.isEmpty(useState.invalidParamsIndex) && !state.isOrderByInvalid && !state.isFidInvalid;
        };
        const submit = () => {
            if (checkParams()) submitRoute();
        };
        Object.assign(useQueryFormLateBinding, { parseRoute }); // assign() will prevent losing ref

        const inputTextMatchParamPlaceholder = (p: Params[typeof paramsNameByType.text[number]]) => {
            if (p.subParam.matchBy === 'implicit') return '模糊';
            return `${p.subParam.matchBy === 'explicit' ? '精确' : '正则'}匹配 空格${p.subParam.spaceSplit ? '不' : ''}分割关键词`;
        };
        const currentQueryTypeDesc = computed(() => {
            if (currentQueryType() === 'fid') return '按吧索引查询';
            if (currentQueryType() === 'postID') return '按贴索引查询';
            if (currentQueryType() === 'search') return '搜索查询';
            return '空查询';
        });

        return { _, ...toRefs(state), ...toRefs(useState), currentQueryTypeDesc, inputTextMatchParamPlaceholder, paramRowLastDomClass, addParam, changeParam, deleteParam, submit };
    }
});
</script>

<style>
.ant-calendar-picker-input {
    border-bottom-left-radius: 0;
    border-top-left-radius: 0;
}
</style>

<style scoped>
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
    padding-left: 22px;
    padding-right: 10px;
}
</style>
