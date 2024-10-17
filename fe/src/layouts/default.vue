<template>
<div class="d-flex flex-column" id="app-wrapper">
    <header>
        <GlobalNavBar />
        <MinimumResolutionWarning />
    </header>
    <img
        v-show="routeUpdatingStore.isUpdating" :src="iconLoadingBlock"
        alt="loading" id="global-loading-block" />
    <div
        v-show="globalLoadingStore.isLoading" class="spinner-border text-primary"
        role="status" id="global-loading-spinner">
        <span class="visually-hidden">Loading...</span>
    </div>
    <main>
        <AConfigProvider :locale="AntdZhCn">
            <slot v-if="!routeUpdatingStore.isUpdating" />
        </AConfigProvider>
    </main>
    <footer class="text-light pt-4 mt-auto" id="footer-upper">
        <footer class="text-center p-3" id="footer-lower">
            <span>{{ config.footerText }}</span>
        </footer>
    </footer>
</div>
</template>

<script setup lang="ts">
import iconLoadingBlock from 'assets/icon-loading-block.svg';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';

const config = useRuntimeConfig().public;
const routeUpdatingStore = useRouteUpdatingStore();
const globalLoadingStore = useGlobalLoadingStore();

useNoScript(`<style>
    #app-wrapper {
        pointer-events: unset !important;
    }
    #global-loading-spinner {
        display: none;
    }
</style>`);
const appPointerEvents = ref('none');
if (import.meta.client) {
    globalLoadingStore.start();
    onNuxtReady(() => {
        globalLoadingStore.stop();
        appPointerEvents.value = 'unset';
    });
}
</script>

<style scoped>
#app-wrapper {
    min-height: 100vh;
    pointer-events: v-bind(appPointerEvents);
}

#footer-upper {
    background-color: #2196f3;
}
#footer-lower {
    background-color: rgba(0, 0, 0, .2);
}

#global-loading-spinner {
    position: absolute;
    right: 1rem;
    top: 1rem;
}
#global-loading-block {
    height: 200px;
    margin: auto;
}
</style>
