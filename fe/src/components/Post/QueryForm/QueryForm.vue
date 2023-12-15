<template>
    <form @submit.prevent="queryFormSubmit" class="mt-3">
        <div class="row">
            <label class="col-1 col-form-label" for="paramFid">贴吧</label>
            <div class="col-3">
                <div class="input-group">
                    <span class="input-group-text"><FontAwesomeIcon icon="filter" /></span>
                    <select v-model.number="uniqueParams.fid.value" id="paramFid"
                            :class="{ 'is-invalid': isFidInvalid }" class="form-select form-control">
                        <option value="0">未指定</option>
                        <option v-for="forum in forumList"
                                :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                    </select>
                </div>
            </div>
            <label class="col-1 col-form-label text-center">帖子类型</label>
            <div class="col my-auto">
                <div class="input-group">
                    <div class="form-check form-check-inline">
                        <input v-model="uniqueParams.postTypes.value" id="paramPostTypesThread"
                               type="checkbox" value="thread" class="form-check-input" />
                        <label class="form-check-label" for="paramPostTypesThread">主题帖</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input v-model="uniqueParams.postTypes.value" id="paramPostTypesReply"
                               type="checkbox" value="reply" class="form-check-input" />
                        <label class="form-check-label" for="paramPostTypesReply">回复帖</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input v-model="uniqueParams.postTypes.value" id="paramPostTypesSubReply"
                               type="checkbox" value="subReply" class="form-check-input" />
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
                    <select v-model="uniqueParams.orderBy.value"
                            :class="{ 'is-invalid': isOrderByInvalid }" class="form-select form-control">
                        <option value="default">默认（按帖索引查询按帖子ID正序，按吧索引/搜索查询按发帖时间倒序）</option>
                        <option value="postedAt">发帖时间</option>
                        <optgroup label="帖子ID">
                            <option value="tid">主题帖tid</option>
                            <option value="pid">回复帖pid</option>
                            <option value="spid">楼中楼spid</option>
                        </optgroup>
                    </select>
                    <select v-show="uniqueParams.orderBy.value !== 'default'"
                            v-model="uniqueParams.orderBy.subParam.direction"
                            class="form-select form-control">
                        <option value="ASC">正序（从小到大，旧到新）</option>
                        <option value="DESC">倒序（从大到小，新到旧）</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="query-params">
            <div v-for="(p, pI) in params" :key="pI" class="input-group">
                <button @click="deleteParam(pI)" class="btn btn-link" type="button">
                    <FontAwesomeIcon icon="times" />
                </button>
                <SelectParam @paramChange="changeParam(pI, $event)" :currentParam="p.name"
                             class="select-param" :class="{
                                 'is-invalid': invalidParamsIndex.includes(pI)
                             }" />
                <div class="param-input-group-text input-group-text">
                    <div class="form-check">
                        <input v-model="p.subParam.not" :id="`param${_.upperFirst(p.name)}Not-${pI}`"
                               type="checkbox" value="good" class="form-check-input" />
                        <label :for="`param${_.upperFirst(p.name)}Not-${pI}`"
                               class="text-secondary fw-bold form-check-label">非</label>
                    </div>
                </div>
                <template v-if="isPostIDParam(p)">
                    <SelectRange v-model="p.subParam.range" />
                    <InputNumericParam @update:modelValue="v => params[pI] = v"
                                       :modelValue="params[pI] as KnownNumericParams"
                                       :placeholders="getPostIDParamPlaceholders(p)" />
                </template>
                <template v-if="isDateTimeParam(p)">
                    <RangePicker v-model="p.subParam.range" :showTime="true"
                                 format="YYYY-MM-DD HH:mm" valueFormat="YYYY-MM-DDTHH:mm" size="large" />
                </template>
                <template v-if="isTextParam(p)">
                    <input v-model="p.value" :placeholder="inputTextMatchParamPlaceholder(p)"
                           type="text" class="form-control" required />
                    <InputTextMatchParam @update:modelValue="v => params[pI] = v"
                                         :modelValue="params[pI] as KnownTextParams"
                                         :paramIndex="pI" />
                </template>
                <template v-if="['threadViewCount', 'threadShareCount', 'threadReplyCount', 'replySubReplyCount'].includes(p.name)">
                    <SelectRange v-model="p.subParam.range" />
                    <InputNumericParam @update:modelValue="v => params[pI] = v"
                                       :modelValue="params[pI] as KnownNumericParams"
                                       :paramIndex="pI"
                                       :placeholders="{ IN: '100,101,102,...', BETWEEN: '100,200', single: '100' }" />
                </template>
                <div v-if="p.name === 'threadProperties'">
                    <div class="param-input-group-text input-group-text">
                        <div class="form-check">
                            <input v-model="p.value" :id="`paramThreadPropertiesGood-${pI}`"
                                   type="checkbox" value="good" class="form-check-input" />
                            <label :for="`paramThreadPropertiesGood-${pI}`"
                                   class="text-danger fw-normal form-check-label">精品</label>
                        </div>
                    </div>
                    <div class="param-input-group-text input-group-text">
                        <div class="form-check">
                            <input v-model="p.value" :id="`paramThreadPropertiesSticky-${pI}`"
                                   type="checkbox" value="sticky" class="form-check-input" />
                            <label :for="`paramThreadPropertiesSticky-${pI}`"
                                   class="text-primary fw-normal form-check-label">置顶</label>
                        </div>
                    </div>
                </div>
                <template v-if="['authorUid', 'latestReplierUid'].includes(p.name)">
                    <SelectRange v-model="p.subParam.range" />
                    <InputNumericParam @update:modelValue="v => params[pI] = v"
                                       :modelValue="params[pI] as KnownNumericParams"
                                       :placeholders="uidParamPlaceholders" />
                </template>
                <template v-if="p.name === 'authorManagerType'">
                    <select v-model="p.value" class="form-control flex-grow-0 w-25">
                        <option value="NULL">吧友</option>
                        <option value="manager">吧主</option>
                        <option value="assist">小吧主</option>
                        <option value="voiceadmin">语音小编</option>
                    </select>
                </template>
                <template v-if="['authorGender', 'latestReplierGender'].includes(p.name)">
                    <select v-model="p.value" class="form-control flex-grow-0 w-25">
                        <option selected value="0">未设置（显示为男）</option>
                        <option value="1">男 ♂</option>
                        <option value="2">女 ♀</option>
                    </select>
                </template>
                <template v-if="p.name === 'authorExpGrade'">
                    <SelectRange v-model="p.subParam.range" />
                    <InputNumericParam @update:modelValue="v => params[pI] = v"
                                       :modelValue="params[pI] as KnownNumericParams"
                                       :placeholders="{ IN: '9,10,11,...', BETWEEN: '9,18', single: '18' }" />
                </template>
            </div>
        </div>
        <div class="row mt-2">
            <button class="add-param-button col-auto btn btn-link disabled" type="button">
                <FontAwesomeIcon icon="plus" />
            </button>
            <SelectParam @paramChange="addParam($event)" currentParam="add" />
        </div>
        <div class="row mt-3">
            <button :disabled="isLoading" class="col-auto btn btn-primary" type="submit">
                查询 <span v-show="isLoading" class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true" />
            </button>
            <span class="col-auto ms-3 my-auto text-muted">{{ currentQueryTypeDesc }}</span>
        </div>
    </form>
</template>

<script setup lang="ts">
import InputNumericParam from './widgets/InputNumericParam.vue';
import InputTextMatchParam, { inputTextMatchParamPlaceholder } from './widgets/InputTextMatchParam.vue';
import SelectParam from './widgets/SelectParam.vue';
import SelectRange from './widgets/SelectRange.vue';

import { isRouteUpdateTriggeredBySubmitQueryForm } from '@/views/Post.vue';
import type { AddNameToParam, KnownDateTimeParams, KnownNumericParams, KnownParams, KnownTextParams, KnownUniqueParams, NamelessParamNumeric, RequiredPostTypes } from './queryParams';
import { orderByRequiredPostTypes, paramsNameByType, paramsRequiredPostTypes, useQueryFormWithUniqueParams } from './queryParams';
import type { ApiForumList } from '@/api/index.d';
import type { ObjValues, PostID, PostType, Writable } from '@/shared';
import { notyShow, postID, removeEnd } from '@/shared';
import { assertRouteNameIsStr, routeNameSuffix } from '@/router';

import { computed, ref, watch } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { useRouter } from 'vue-router';
import { RangePicker } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';

const router = useRouter();
defineProps<{
    isLoading: boolean,
    forumList: ApiForumList
}>();
const {
    uniqueParams,
    params,
    invalidParamsIndex,
    addParam,
    changeParam,
    deleteParam,
    fillParamDefaultValue,
    clearParamDefaultValue,
    clearedParamsDefaultValue,
    clearedUniqueParamsDefaultValue,
    flattenParams,
    parseParamRoute,
    submitParamRoute
} = useQueryFormWithUniqueParams();
const isOrderByInvalid = ref(false);
const isFidInvalid = ref(false);

type Param = ObjValues<KnownParams>;
const isDateTimeParam = (param: Param): param is KnownDateTimeParams =>
    (paramsNameByType.dateTime as Writable<typeof paramsNameByType.dateTime> as string[]).includes(param.name);
const isTextParam = (param: Param): param is KnownTextParams =>
    (paramsNameByType.text as Writable<typeof paramsNameByType.text> as string[]).includes(param.name);
const isPostIDParam = (param: Param): param is AddNameToParam<PostID, NamelessParamNumeric> =>
    (postID as Writable<typeof postID> as string[]).includes(param.name);
const getPostIDParamPlaceholders = (p: Param) => ({
    IN: p.name === 'tid' ? '5000000000,5000000001,5000000002,...' : '15000000000,15000000001,15000000002,...',
    BETWEEN: p.name === 'tid' ? '5000000000,6000000000' : '15000000000,16000000000',
    single: p.name === 'tid' ? '5000000000' : '15000000000'
});
const uidParamPlaceholders = {
    IN: '4000000000,4000000001,4000000002,...',
    BETWEEN: '4000000000,5000000000',
    single: '4000000000'
};

const getCurrentQueryType = () => {
    const clearedParams = clearedParamsDefaultValue(); // not including unique params
    if (_.isEmpty(clearedParams)) { // is there no other params
        // ignore the post type param since index query (postID or fid) doesn't restrict them
        const clearedUniqueParams = _.omit(clearedUniqueParamsDefaultValue(), 'postTypes');
        if (_.isEmpty(clearedUniqueParams)) {
            return 'empty'; // only fill unique param postTypes and/or orderBy doesn't query anything
        } else if (clearedUniqueParams.fid !== undefined) {
            // note when query with postTypes and/or orderBy param, the route will go params instead of fid
            return 'fid';
        }
    }
    // is there no other params except post id params
    if (_.isEmpty(_.reject(clearedParams, isPostIDParam))
        // is there only one post id param
        && _.filter(clearedParams, isPostIDParam).length === 1
        // is all post ID params doesn't own any sub param
        && _.chain(clearedParams).map('subParam').filter().isEmpty().value()
    ) return 'postID';

    return 'search';
};
const currentQueryTypeDesc = computed(() => {
    const currentQueryType = getCurrentQueryType();
    if (currentQueryType === 'fid') return '按吧索引查询';
    if (currentQueryType === 'postID') return '按帖索引查询';
    if (currentQueryType === 'search') return '搜索查询';
    return '空查询';
});

const submitRoute = () => { // decide that route to go
    const clearedParams = clearedParamsDefaultValue();
    const clearedUniqueParams = clearedUniqueParamsDefaultValue();
    if (_.isEmpty(clearedUniqueParams)) { // check whether query by post id or not
        for (const postIDName of _.reverse(postID)) {
            const postIDParam = _.filter(clearedParams, p => p.name === postIDName);
            if (_.isEmpty(_.reject(clearedParams, p => p.name === postIDName)) // is there no other params
                && postIDParam.length === 1 // is there only one post id param
                && postIDParam[0]?.subParam === undefined) { // is range subParam not set
                router.push({ name: `post/${postIDName}`, params: { [postIDName]: String(postIDParam[0].value) } });
                return; // exit early to prevent pushing other route
            }
        }
    }
    if (clearedUniqueParams.fid !== undefined
        && _.isEmpty(clearedParams)
        && _.isEmpty(_.omit(clearedUniqueParams, 'fid'))) { // fid route
        router.push({ name: 'post/fid', params: { fid: clearedUniqueParams.fid.value } });
        return;
    }
    submitParamRoute(clearedUniqueParams, clearedParams); // param route
};
const queryFormSubmit = () => {
    isRouteUpdateTriggeredBySubmitQueryForm.value = true;
    submitRoute();
};
const checkParams = () => {
    // check query type
    isFidInvalid.value = false;
    const clearedUniqueParams = clearedUniqueParamsDefaultValue();
    const currentQueryType = getCurrentQueryType();
    switch (currentQueryType) {
        case 'empty':
            notyShow('warning', '请选择贴吧或/并输入查询参数<br />勿只选择帖子类型参数');
            return false; // exit early
        case 'postID':
            if (clearedUniqueParams.fid !== undefined) {
                uniqueParams.value.fid.value = 0; // reset fid to default,
                notyShow('info', '已移除按帖索引查询所不需要的查询贴吧参数');
                submitRoute(); // update route to match new params without fid
            }
            break;
        case 'search':
            if (clearedUniqueParams.fid === undefined) {
                isFidInvalid.value = true; // search query require fid param
                notyShow('warning', '搜索查询必须指定查询贴吧');
            }
            break;
        case 'fid':
    }

    const isRequiredPostTypes = (current: PostType[], required?: RequiredPostTypes[string]): required is undefined => {
        if (required === undefined) return true; // not set means this param accepts any post types
        required[1] = _.sortBy(required[1]);
        if (required[0] === 'SUB' && _.isEmpty(_.difference(current, required[1]))) return true;
        return required[0] === 'ALL' && _.isEqual(required[1], current);
    };
    const requiredPostTypesToString = (required: NonNullable<RequiredPostTypes[string]>) =>
        `${required[1].join(required[0] === 'SUB' ? ' | ' : ' & ')}`;
    const postTypes = _.sortBy(uniqueParams.value.postTypes.value);

    // check params required post types, index query doesn't restrict on post types
    invalidParamsIndex.value = []; // reset to prevent duplicate indexes
    if (currentQueryType !== 'postID' && currentQueryType !== 'fid') {
        // we don't filter() here for post types validate
        params.value.map(clearParamDefaultValue).forEach((param, paramIndex) => {
            if (param?.name === undefined || param.value === undefined) {
                invalidParamsIndex.value.push(paramIndex);
            } else {
                const required = paramsRequiredPostTypes[param.name];
                if (!isRequiredPostTypes(postTypes, required)) {
                    invalidParamsIndex.value.push(paramIndex);
                    notyShow('warning',
                        `第${paramIndex + 1}个${param.name}参数要求帖子类型为${requiredPostTypesToString(required)}`);
                }
            }
        });
    }

    // check order by required post types
    isOrderByInvalid.value = false;
    const orderBy = uniqueParams.value.orderBy.value;
    if (orderBy in orderByRequiredPostTypes) {
        const required = orderByRequiredPostTypes[orderBy];
        if (!isRequiredPostTypes(postTypes, required)) {
            isOrderByInvalid.value = true;
            notyShow('warning', `排序方式与查询帖子类型要求不匹配<br />当前要求帖子类型为${requiredPostTypesToString(required)}`);
        }
    }

    // return false when there have at least one invalid params
    return _.isEmpty(invalidParamsIndex.value) && !isOrderByInvalid.value && !isFidInvalid.value;
};

const parseRoute = (route: RouteLocationNormalizedLoaded) => {
    assertRouteNameIsStr(route.name);
    uniqueParams.value = _.mapValues(uniqueParams.value, _.unary(fillParamDefaultValue)) as KnownUniqueParams;
    params.value = [];
    const routeName = removeEnd(route.name, routeNameSuffix.page);
    // parse route path to params
    if (routeName === 'post/param' && _.isArray(route.params.pathMatch)) {
        parseParamRoute(route.params.pathMatch); // omit the page param from route full path
    } else if (routeName === 'post/fid' && !_.isArray(route.params.fid)) {
        uniqueParams.value.fid.value = parseInt(route.params.fid);
    } else { // post id routes
        uniqueParams.value = _.mapValues(uniqueParams.value, param =>
            fillParamDefaultValue(param, true)) as KnownUniqueParams; // reset to default
        params.value = _.map(_.omit(route.params, 'pathMatch', 'page'), (value, name) =>
            fillParamDefaultValue({ name, value }));
    }
};
const parseRouteToGetFlattenParams
    = (route: RouteLocationNormalizedLoaded): ReturnType<typeof flattenParams> | false => {
        parseRoute(route);
        if (checkParams()) return flattenParams();
        return false;
    };

watch(() => uniqueParams.value.postTypes.value, (to, from) => {
    if (_.isEmpty(to)) uniqueParams.value.postTypes.value = from; // to prevent empty post types
});

defineExpose({ getCurrentQueryType, parseRouteToGetFlattenParams });
</script>

<style>
.input-group-text ~ * > .ant-input-lg {
    height: unset; /* revert the effect in global style @/shared/style.css */
}
/* remove borders for <RangePicker> in the start, middle and end of .input-group */
.input-group > :not(:first-child) .ant-calendar-picker-input {
    border-bottom-left-radius: 0;
    border-top-left-radius: 0;
}
.input-group > :not(:last-child) .ant-calendar-picker-input {
    border-bottom-right-radius: 0;
    border-top-right-radius: 0;
}
</style>

<style scoped>
.query-params > * {
    margin-top: -1px;
}
.query-params > :first-child > .select-param {
    border-top-left-radius: .25rem !important;
}
.query-params > :last-child > .select-param {
    border-bottom-left-radius: .25rem !important;
}

.query-params > :first-child:not(:only-child) > :last-child {
    border-bottom-right-radius: 0;
}
.query-params > :not(:first-child):not(:last-child) > :last-child {
    border-bottom-right-radius: 0;
    border-top-right-radius: 0;
}
.query-params > :last-child:not(:only-child) > :last-child {
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
