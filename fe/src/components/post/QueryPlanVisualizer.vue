<template>
<div class="query-plan-visualizer d-flex flex-wrap">
    <ClientOnly>
        <div class="d-inline-flex justify-content-center w-100 border-bottom">
            <span class="align-self-center">查询计划：</span>
            <select v-model="selectedPage" class="form-select">
                <option
                    v-for="page in data.pages"
                    :key="page.pages.currentCursor"
                    :value="page.pages.currentCursor">
                    {{ cursorTemplate(page.pages.currentCursor) }}
                </option>
            </select>
        </div>
        <DefinePlan v-slot="{ query }">
            <Plan
                v-if="query !== undefined" :planQuery="query.query"
                :planSource="JSON.stringify(query.plan, null, 4)" class="pev2" />
        </DefinePlan>
        <ReusePlan
            :key="selectedPage" v-if="selectedPage !== undefined"
            :query="data.pages
                .find(page => page.pages.currentCursor === selectedPage)
                ?.query" />
    </ClientOnly>
</div>
</template>

<script setup lang="ts">
import type { InfiniteData } from '@tanstack/vue-query';
import { Plan } from 'pev2';
import 'pev2/dist/pev2.css';

const { data } = defineProps<{ data: InfiniteData<ApiPosts['response']> }>();
const selectedPage = ref<Cursor>();
const [DefinePlan, ReusePlan] = createReusableTemplate<{ query?: ApiPosts['response']['query'] }>();
});
</script>

<style scoped>
select {
    width: 30rem;
}

.is-hydrating-or-ssr .query-plan-visualizer {
    height: 3rem;
}

.pev2 {
    height: 53rem;
    resize: block;
    contain: content;
}
</style>
