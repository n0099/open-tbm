<template>
<form @submit.prevent="queryFormSubmit()" class="mt-3">
    <div class="row">
        <label class="col-1 col-form-label text-center">帖子类型</label>
        <div class="col my-auto">
            <div class="input-group">
                <div v-for="(postType, index) in postType" :key="postType" class="form-check form-check-inline">
                    <input
                        v-model="uniqueParams.postTypes.value" :id="`paramPostTypes${_.upperFirst(postType)}`"
                        type="checkbox" :value="postType" class="form-check-input" />
                    <label class="form-check-label" :for="`paramPostTypes${_.upperFirst(postType)}`">
                        {{ postTypeText[index] }}
                    </label>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-2 mb-3">
        <label class="col-1 col-form-label" for="paramOrder">排序方式</label>
        <div class="col-8">
            <div v-for="orderBy in [uniqueParams.orderBy]" :key="orderBy.value" class="input-group">
                <span class="input-group-text"><FontAwesome :icon="faSortAmountDown" /></span>
                <select
                    v-model="orderBy.value" id="paramOrder"
                    :class="{ 'is-invalid': isOrderByInvalid }" class="form-select form-control">
                    <option value="default">
                        默认（按帖索引查询按帖子ID正序，按吧索引/搜索查询按发帖时间倒序）
                    </option>
                    <option value="postedAt">发帖时间</option>
                    <optgroup label="帖子ID">
                        <option v-for="(postID, index) in postID" :key="postID" :value="postID">
                            {{ postTypeText[index] }}{{ postID }}
                        </option>
                    </optgroup>
                </select>
                <select
                    v-show="orderBy.value !== 'default'"
                    v-model="orderBy.subParam.direction"
                    id="paramOrderBy" class="form-select form-control">
                    <option value="ASC">
                        正序（从小到大，旧到新）
                    </option>
                    <option value="DESC">
                        倒序（从大到小，新到旧）
                    </option>
                </select>
            </div>
        </div>
    </div>
    <DefineSuspenseLoading v-slot="{ $slots }">
        <Suspense :timeout="0">
            <template #fallback>
                <div class="input-group-text">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </template>
            <component :is="$slots.default" />
        </Suspense>
    </DefineSuspenseLoading>
    <div class="query-params">
        <div v-for="(p, pI) in params" :key="pI" class="input-group">
            <button @click="deleteParam(pI)" class="btn btn-link" type="button">
                <FontAwesome :icon="faTimes" />
            </button>
            <PostQueryFormWidgetSelectParam
                @paramChange="changeParam(pI, $event)" :currentParam="p.name"
                class="select-param" :class="{ 'is-invalid': invalidParamsIndex.includes(pI) }" />
            <div class="input-group-text">
                <div class="form-check">
                    <input
                        v-model="p.subParam.not" :id="`param${_.upperFirst(p.name)}Not-${pI}`"
                        type="checkbox" value="good" class="form-check-input" />
                    <label
                        :for="`param${_.upperFirst(p.name)}Not-${pI}`"
                        class="text-secondary fw-bold form-check-label">非</label>
                </div>
            </div>
            <template v-if="p.name === 'fid'">
                <ReuseSuspenseLoading>
                    <LazyWidgetSelectForum v-model.number="p.value" class="flex-grow-0 w-50">
                        <template #indicators="{ renderer }">
                            <div class="input-group-text"><RenderFunction :renderer="renderer" /></div>
                        </template>
                    </LazyWidgetSelectForum>
                </ReuseSuspenseLoading>
            </template>
            <template v-if="isPostIDParam(p)">
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                </ReuseSuspenseLoading>
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetInputNumericParam
                        @update:modelValue="onParamUpdate(pI, $event)"
                        :modelValue="params[pI] as KnownNumericParams"
                        :placeholders="getPostIDParamPlaceholders(p)" />
                </ReuseSuspenseLoading>
            </template>
            <template v-if="isDateTimeParam(p)">
                <ReuseSuspenseLoading>
                    <LazyARangePicker
                        v-model:value="p.subParam.range" showTime
                        format="YYYY-MM-DD HH:mm" valueFormat="YYYY-MM-DDTHH:mm" size="large" />
                </ReuseSuspenseLoading>
            </template>
            <template v-if="isTextParam(p)">
                <input
                    v-model="p.value" :placeholder="inputTextParamPlaceholder(p)"
                    type="text" class="form-control" required />
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetInputTextParam
                        @update:modelValue="onParamUpdate(pI, $event)"
                        :modelValue="params[pI] as KnownTextParams"
                        :paramIndex="pI" />
                </ReuseSuspenseLoading>
            </template>
            <template
                v-if=" // https://github.com/microsoft/TypeScript/issues/51678
                    p.name === 'threadViewCount'
                        || p.name === 'threadShareCount'
                        || p.name === 'threadReplyCount'
                        || p.name === 'replySubReplyCount'">
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                </ReuseSuspenseLoading>
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetInputNumericParam
                        @update:modelValue="onParamUpdate(pI, $event)"
                        :modelValue="params[pI] as KnownNumericParams"
                        :paramIndex="pI"
                        :placeholders="{ IN: '100,101,102,...', BETWEEN: '100,200', equals: '100' }" />
                </ReuseSuspenseLoading>
            </template>
            <template v-if="p.name === 'threadProperties'">
                <div v-for="property in ['good', 'sticky']" :key="property" class="input-group-text">
                    <div class="form-check">
                        <input
                            v-model="p.value" :id="`paramThreadProperties${_.upperFirst(property)}-${pI}`"
                            type="checkbox" :value="property" class="form-check-input" />
                        <label
                            :for="`paramThreadProperties${_.upperFirst(property)}-${pI}`"
                            class="text-danger fw-normal form-check-label">
                            <template v-if="property === 'good'">精品</template>
                            <template v-else-if="property === 'sticky'">置顶</template>
                        </label>
                    </div>
                </div>
            </template>
            <template v-if="p.name === 'authorUid' || p.name === 'latestReplierUid'">
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                </ReuseSuspenseLoading>
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetInputNumericParam
                        @update:modelValue="onParamUpdate(pI, $event)"
                        :modelValue="params[pI] as KnownNumericParams"
                        :placeholders="uidParamsPlaceholder" />
                </ReuseSuspenseLoading>
            </template>
            <template v-if="p.name === 'authorManagerType'">
                <select v-model="p.value" class="form-control flex-grow-0 w-25">
                    <option value="NULL">吧友</option>
                    <option
                        v-for="[moderatorType, moderatorTypeDescription] in Object.entries(knownModeratorTypes)
                            .flatMap(([key, value]) => [key, value])"
                        :key="moderatorType" :value="moderatorType">
                        {{ moderatorTypeDescription }}
                    </option>
                </select>
            </template>
            <template v-if="['authorGender', 'latestReplierGender'].includes(p.name)">
                <select v-model="p.value" class="form-control flex-grow-0 w-25">
                    <option value="0">未设置（显示为男）</option>
                    <option value="1">男 ♂</option>
                    <option value="2">女 ♀</option>
                </select>
            </template>
            <template v-if="p.name === 'authorExpGrade'">
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetSelectRange v-model="p.subParam.range" />
                </ReuseSuspenseLoading>
                <ReuseSuspenseLoading>
                    <LazyPostQueryFormWidgetInputNumericParam
                        @update:modelValue="onParamUpdate(pI, $event)"
                        :modelValue="params[pI] as KnownNumericParams"
                        :placeholders="{ IN: '9,10,11,...', BETWEEN: '9,18', equals: '18' }" />
                </ReuseSuspenseLoading>
            </template>
        </div>
    </div>
    <div class="row mt-2">
        <button class="add-param-button col-auto btn btn-link disabled" type="button">
            <FontAwesome :icon="faPlus" />
        </button>
        <PostQueryFormWidgetSelectParam :key="params.length" @paramChange="addParam($event)" currentParam="add" />
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
import { inputTextParamPlaceholder } from '@/components/post/queryForm/widget/InputTextParam.vue';
import { faPlus, faSortAmountDown, faTimes } from '@fortawesome/free-solid-svg-icons';
import _ from 'lodash';

const { queryFormDeps } = defineProps<{
    isLoading: boolean,
    queryFormDeps: QueryFormDeps
}>();
const { // https://github.com/orgs/vuejs/discussions/6147
    isOrderByInvalid,
    currentQueryType,
    generateRoute,
    uniqueParams,
    params,
    invalidParamsIndex,
    addParam,
    changeParam,
    deleteParam
} = queryFormDeps;
const [DefineSuspenseLoading, ReuseSuspenseLoading] = createReusableTemplate();

const getPostIDParamPlaceholders = (p: Param) => ({
    IN: p.name === 'tid' ? '5000000000,5000000001,5000000002,...' : '15000000000,15000000001,15000000002,...',
    BETWEEN: p.name === 'tid' ? '5000000000,6000000000' : '15000000000,16000000000',
    equals: p.name === 'tid' ? '5000000000' : '15000000000'
});
const onParamUpdate = (index: number, event: KnownTextParams | KnownNumericParams) => { params.value[index] = event };
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
.spinner-border {
    height: 1.5rem;
    width: 1.5rem;
}
</style>
