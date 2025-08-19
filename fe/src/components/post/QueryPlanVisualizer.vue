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
            <Suspense :timeout="0">
                <template #fallback>
                    <PlaceholderPostList isLoading class="loading-placeholder w-100" />
                </template>
                <LazyPlan
                    v-if="query !== undefined" :planQuery="query.query"
                    :planSource="JSON.stringify(query.plan, null, 4)" class="pev2" />
            </Suspense>
        </DefinePlan>
        <ReusePlan
            :key="`${route.path}/cursor/${selectedPage}`" v-if="selectedPage !== undefined"
            :query="data.pages
                .find(page => page.pages.currentCursor === selectedPage)
                ?.query" />
    </ClientOnly>
</div>
</template>

<script setup lang="ts">
import type { InfiniteData } from '@tanstack/vue-query';

const { data } = defineProps<{ data: InfiniteData<ApiPosts['response']> }>();
const route = useRoute();
const selectedPage = ref<Cursor>();
const [DefinePlan, ReusePlan] = createReusableTemplate<{ query?: ApiPosts['response']['query'] }>();
const LazyPlan = defineAsyncComponent(async () => {
    // https://github.com/dalibo/pev2/commit/06dcea951248163fb8974a05eb96577e45059c07
    useNuxtApp().vueApp.use((await import('vue-tippy')).default, {
        directive: 'vue-tippy', // prevent conflicated with ./tippy.ts https://github.com/KABBOUCHI/vue-tippy/blob/ca6716c307ad284dd33d8936f67025ada8830637/src/plugin/index.ts#L12
        defaultProps: { theme: 'light' }
    });
    await import('pev2/dist/pev2.css');

    return (await import('pev2')).Plan;
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
    resize: block;
    contain: content;
}
.pev2, .loading-placeholder {
    height: 53rem;
}
</style>
