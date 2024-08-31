<template>
<form @submit.prevent="_ => queryFormSubmit()" class="mt-3">
    <div class="row">
        <label class="col-1 col-form-label" for="paramFid">贴吧</label>
        <div class="col-3">
            <div class="input-group">
                <span class="input-group-text"><FontAwesome :icon="faFilter" /></span>
                <WidgetSelectForum
                    v-model.number="uniqueParams.fid.value"
                    :class="{ 'is-invalid': isFidInvalid }" id="paramFid">
                    <template #indicators="{ renderer }">
                        <span class="input-group-text"><RenderFunction :renderer="renderer" /></span>
                    </template>
                </WidgetSelectForum>
            </div>
        </div>
        <label class="col-1 col-form-label text-center">帖子类型</label>
        <div class="col my-auto">
            <div class="input-group">
                <div class="form-check form-check-inline">
                    <input
                        v-model="uniqueParams.postTypes.value" type="checkbox"
                        value="thread" class="form-check-input" id="paramPostTypesThread" />
                    <label class="form-check-label" for="paramPostTypesThread">主题帖</label>
                </div>
                <div class="form-check form-check-inline">
                    <input
                        v-model="uniqueParams.postTypes.value" type="checkbox"
                        value="reply" class="form-check-input" id="paramPostTypesReply" />
                    <label class="form-check-label" for="paramPostTypesReply">回复帖</label>
                </div>
                <div class="form-check form-check-inline">
                    <input
                        v-model="uniqueParams.postTypes.value" type="checkbox"
                        value="subReply" class="form-check-input" id="paramPostTypesSubReply" />
                    <label class="form-check-label" for="paramPostTypesSubReply">楼中楼</label>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-2 mb-3">
        <label class="col-1 col-form-label" for="paramOrder">排序方式</label>
        <div class="col-8">
            <div class="input-group">
                <span class="input-group-text"><FontAwesome :icon="faSortAmountDown" /></span>
                <select
                    v-model="uniqueParams.orderBy.value"
                    :class="{ 'is-invalid': isOrderByInvalid }"
                    class="form-select form-control" id="paramOrder">
                    <option value="default">默认（按帖索引查询按帖子ID正序，按吧索引/搜索查询按发帖时间倒序）</option>
                    <option value="postedAt">发帖时间</option>
                    <optgroup label="帖子ID">
                        <option value="tid">主题帖tid</option>
                        <option value="pid">回复帖pid</option>
                        <option value="spid">楼中楼spid</option>
                    </optgroup>
                </select>
                <select
                    v-show="uniqueParams.orderBy.value !== 'default'"
                    v-model="uniqueParams.orderBy.subParam.direction"
                    class="form-select form-control" id="paramOrderBy">
                    <option value="ASC">正序（从小到大，旧到新）</option>
                    <option value="DESC">倒序（从大到小，新到旧）</option>
                </select>
            </div>
        </div>
    </div>
    <div class="query-params">
        <div v-for="(p, pI) in params" :key="pI" class="input-group">
            <button @click="_ => deleteParam(pI)" class="btn btn-link" type="button">
                <FontAwesome :icon="faTimes" />
            </button>
            <PostQueryFormWidgetSelectParam
                @paramChange="e => changeParam(pI, e)" :currentParam="p.name"
                class="select-param" :class="{
                    'is-invalid': invalidParamsIndex.includes(pI)
                }" />
            <div class="input-group-text">
                <div class="form-check">
                    <input
                        v-model="p.subParam.not" type="checkbox" value="good" class="form-check-input"
                        :id="`param${_.upperFirst(p.name)}Not-${pI}`" />
                    <label
                        :for="`param${_.upperFirst(p.name)}Not-${pI}`"
                        class="text-secondary fw-bold form-check-label">非</label>
                </div>
            </div>
            <template v-if="isPostIDParam(p)">
                <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                <LazyPostQueryFormWidgetInputNumericParam
                    @update:modelValue="e => { params[pI] = e }"
                    :modelValue="params[pI] as KnownNumericParams"
                    :placeholders="getPostIDParamPlaceholders(p)" />
            </template>
            <template v-if="isDateTimeParam(p)">
                <LazyARangePicker
                    v-model:value="p.subParam.range" showTime
                    format="YYYY-MM-DD HH:mm" valueFormat="YYYY-MM-DDTHH:mm" size="large" />
            </template>
            <template v-if="isTextParam(p)">
                <input
                    v-model="p.value" :placeholder="inputTextMatchParamPlaceholder(p)"
                    type="text" class="form-control" required />
                <PostQueryFormWidgetInputTextMatchParam
                    @update:modelValue="e => { params[pI] = e }"
                    :modelValue="params[pI] as KnownTextParams"
                    :paramIndex="pI" />
            </template>
            <template v-if="['threadViewCount', 'threadShareCount', 'threadReplyCount', 'replySubReplyCount'].includes(p.name)">
                <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                <LazyPostQueryFormWidgetInputNumericParam
                    @update:modelValue="e => { params[pI] = e }"
                    :modelValue="params[pI] as KnownNumericParams"
                    :paramIndex="pI"
                    :placeholders="{ IN: '100,101,102,...', BETWEEN: '100,200', equals: '100' }" />
            </template>
            <template v-if="p.name === 'threadProperties'">
                <div class="input-group-text">
                    <div class="form-check">
                        <input
                            v-model="p.value" type="checkbox" value="good" class="form-check-input"
                            :id="`paramThreadPropertiesGood-${pI}`" />
                        <label
                            :for="`paramThreadPropertiesGood-${pI}`"
                            class="text-danger fw-normal form-check-label">精品</label>
                    </div>
                </div>
                <div class="input-group-text">
                    <div class="form-check">
                        <input
                            v-model="p.value" type="checkbox" value="sticky" class="form-check-input"
                            :id="`paramThreadPropertiesSticky-${pI}`" />
                        <label
                            :for="`paramThreadPropertiesSticky-${pI}`"
                            class="text-primary fw-normal form-check-label">置顶</label>
                    </div>
                </div>
            </template>
            <template v-if="['authorUid', 'latestReplierUid'].includes(p.name)">
                <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                <LazyPostQueryFormWidgetInputNumericParam
                    @update:modelValue="e => { params[pI] = e }"
                    :modelValue="params[pI] as KnownNumericParams"
                    :placeholders="uidParamsPlaceholder" />
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
                <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                <LazyPostQueryFormWidgetInputNumericParam
                    @update:modelValue="e => { params[pI] = e }"
                    :modelValue="params[pI] as KnownNumericParams"
                    :placeholders="{ IN: '9,10,11,...', BETWEEN: '9,18', equals: '18' }" />
            </template>
        </div>
    </div>
    <div class="row mt-2">
        <button class="add-param-button col-auto btn btn-link disabled" type="button">
            <FontAwesome :icon="faPlus" />
        </button>
        <PostQueryFormWidgetSelectParam :key="params.length" @paramChange="e => addParam(e)" currentParam="add" />
    </div>
    <div class="row mt-3">
        <button :disabled="isLoading" class="col-auto btn btn-primary" type="submit">
            查询
            <span v-show="isLoading" class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true">
                <span class="visually-hidden">Loading...</span>
            </span>
        </button>
        <span class="col-auto ms-3 my-auto text-muted">{{ currentQueryTypeDescription }}</span>
        <span v-if="useHydrationStore().isHydratingOrSSR" class="col-auto ms-3 my-auto">
            提交查询表单需要使用 JavaScript
        </span>
    </div>
</form>
</template>

<script setup lang="ts">
import { inputTextMatchParamPlaceholder } from './widget/InputTextMatchParam.vue';
import { faFilter, faPlus, faSortAmountDown, faTimes } from '@fortawesome/free-solid-svg-icons';
import _ from 'lodash';

const props = defineProps<{
    isLoading: boolean,
    queryFormDeps: QueryFormDeps
}>();
const {
    isOrderByInvalid,
    isFidInvalid,
    currentQueryType,
    generateRoute,
    uniqueParams,
    params,
    invalidParamsIndex,
    addParam,
    changeParam,
    deleteParam
} = props.queryFormDeps;

const getPostIDParamPlaceholders = (p: Param) => ({
    IN: p.name === 'tid' ? '5000000000,5000000001,5000000002,...' : '15000000000,15000000001,15000000002,...',
    BETWEEN: p.name === 'tid' ? '5000000000,6000000000' : '15000000000,16000000000',
    equals: p.name === 'tid' ? '5000000000' : '15000000000'
});
const uidParamsPlaceholder = {
    IN: '4000000000,4000000001,4000000002,...',
    BETWEEN: '4000000000,5000000000',
    equals: '4000000000'
};

const currentQueryTypeDescription = computed(() => {
    const queryType = currentQueryType.value;
    if (queryType === 'fid')
        return '按吧索引查询';
    if (queryType === 'postID')
        return '按帖索引查询';
    if (queryType === 'search')
        return '搜索查询';

    return '空查询';
});

const queryFormSubmit = async () => useTriggerRouteUpdateStore()
    .push('<PostQueryForm>@submit')({ ...generateRoute(), force: true });

watch(() => uniqueParams.value.postTypes.value, (to, from) => {
    if (_.isEmpty(to))
        uniqueParams.value.postTypes.value = from; // to prevent empty post types
});
</script>

<style scoped>
:deep(.input-group-text ~ * > .ant-input-lg) {
    block-size: unset; /* revert the effect in global style assets/css/global.css */
}
/* remove borders for <ARangePicker> in the start, middle and end of .input-group */
:deep(.input-group > :not(:first-child) .ant-calendar-picker-input) {
    border-end-start-radius: 0;
    border-start-start-radius: 0;
}
:deep(.input-group > :not(:last-child) .ant-calendar-picker-input) {
    border-end-end-radius: 0;
    border-start-end-radius: 0;
}

.query-params > * {
    margin-block-start: -1px;
}
.query-params > :first-child > .select-param {
    border-start-start-radius: .25rem !important;
}
.query-params > :last-child > .select-param {
    border-end-start-radius: .25rem !important;
}

.query-params > :first-child:not(:only-child) > :last-child {
    border-end-end-radius: 0;
}
.query-params > :not(:first-child):not(:last-child) > :last-child {
    border-end-end-radius: 0;
    border-start-end-radius: 0;
}
.query-params > :last-child:not(:only-child) > :last-child {
    border-start-end-radius: 0;
}

.query-params .input-group-text {
    background-color: unset;
}

.add-param-button { /* fa-plus is wider than fa-times 3px */
    padding-inline-start: 22px;
    padding-inline-end: 10px;
}
</style>
