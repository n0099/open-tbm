<template>
<div
    id="app-wrapper" class="d-flex flex-column gap-3"
    :class="{ 'is-hydrating-or-ssr': hydrationStore.isHydratingOrSSR }">
    <header>
        <GlobalNavBar />
        <MinimumResolutionWarning />
    </header>
    <img
        v-show="routeUpdatingStore.isUpdating" id="global-loading-block"
        :src="iconLoadingBlock" alt="loading" />
    <div
        v-show="globalLoadingStore.isLoading" id="global-loading-spinner"
        class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <main class="mb-auto">
        <AConfigProvider :locale="AntdZhCn">
            <slot v-if="!routeUpdatingStore.isUpdating" />
        </AConfigProvider>
    </main>
    <footer id="footer-upper" class="text-light pt-4">
        <footer id="footer-lower" class="text-center p-3">
            <span>{{ config.footerText }}</span>
        </footer>
    </footer>
</div>
</template>

<script setup lang="ts">
import iconLoadingBlock from '@/assets/icon-loading-block.svg';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';

const config = useRuntimeConfig().public;
const hydrationStore = useHydrationStore();
const routeUpdatingStore = useRouteUpdatingStore();
const globalLoadingStore = useGlobalLoadingStore();
const appPointerEvents = ref('none');
if (import.meta.client) {
    globalLoadingStore.start();
    onNuxtReady(() => {
        globalLoadingStore.stop();
        appPointerEvents.value = 'unset';
    });
}

useNoScript(`<style>
    #app-wrapper {
        pointer-events: unset !important;
    }
    #global-loading-spinner {
        display: none;
    }
</style>`);
</script>

<style scoped>
#app-wrapper {
    min-height: 100dvh;
    pointer-events: v-bind(appPointerEvents);
}

#footer-upper {
    background-color: #2196f3;
}
#footer-lower {
    background-color: rgb(0 0 0 / 20%);
}

#global-loading-spinner {
    position: absolute;
    inset-inline-end: 1rem;
    inset-block-start: 1rem;
}
#global-loading-block {
    height: 200px;
    margin: auto;
}
</style>
