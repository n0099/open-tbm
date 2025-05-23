<template>
<div v-if="data !== undefined" class="query-plan-visualizer">
    <div class="d-inline-flex justify-content-center w-100 border-bottom">
        <span class="align-self-center">查询计划：</span>
        <AMenu v-model:selectedKeys="selectedPostType" mode="horizontal" class="justify-content-center w-25">
            <AMenuItem key="thread">主题帖</AMenuItem>
            <AMenuItem key="reply">回复帖</AMenuItem>
            <AMenuItem key="subReply">楼中楼</AMenuItem>
        </AMenu>
        <select v-model="selectedPage" class="form-select">
            <option
                v-for="page in data.pages"
                :key="page.pages.currentCursor"
                :value="page.pages.currentCursor">
                {{ cursorTemplate(page.pages.currentCursor) }}
            </option>
        </select>
    </div>
    <template v-if="selectedPostType !== undefined">
        <DefinePlan v-slot="{ query }">
            <Plan
                v-if="query !== undefined" :planQuery="query.query"
                :planSource="JSON.stringify(query.plan, null, 4)" />
        </DefinePlan>
        <ReusePlan
            :key="`${selectedPage}/${selectedPostType[0]}`" :query="data.pages
                .find(page => page.pages.currentCursor === selectedPage)
                ?.queries[selectedPostType[0]]" />
    </template>
</div>
</template>

<script setup lang="ts">
import type { InfiniteData } from '@tanstack/vue-query';
import { Plan } from 'pev2';
import 'pev2/dist/pev2.css';

const { data } = defineProps<{ data?: InfiniteData<ApiPosts['response']> }>();
const selectedPostType = ref<[PostType]>();
const selectedPage = ref<Cursor>();
const [DefinePlan, ReusePlan] = createReusableTemplate<{ query?: ApiPosts['response']['queries'][PostType] }>();

watchEffect(() => {
    selectedPage.value = data?.pages.at(-1)?.pages.currentCursor;
});
</script>

<style scoped>
select {
    width: 30rem;
}

.query-plan-visualizer {
    contain: content;
}

.plan-container {
    width: 100%;
    height: 30vh;
    resize: block;
}
</style>
