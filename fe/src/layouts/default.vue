<template>
    <div class="d-flex flex-column" id="app-wrapper">
        <GlobalNavBar />
        <MinimumResolutionWarning />
        <img :src="iconLoadingBlock" :class="{ 'd-none': !isRouteUpdating }" id="loading-block" />
        <AConfigProvider :locale="AntdZhCn">
            <slot :class="{ invisible: isRouteUpdating }" />
        </AConfigProvider>
        <footer class="text-light pt-4 mt-auto" id="footer-upper">
            <div class="text-center">
                <p>
                    <span v-if="isGoogleAnalyticsEnabled">
                        Google <NuxtLink class="text-white"
                                         to="https://www.google.com/analytics/terms/cn.html"
                                         target="_blank">Analytics 服务条款</NuxtLink> |
                        <NuxtLink class="text-white"
                                  to="https://policies.google.com/privacy" target="_blank">Analytics 隐私条款</NuxtLink>
                    </span>
                    <span v-if="isReCAPTCHAEnabled && isGoogleAnalyticsEnabled"> | </span>
                    <NuxtLink v-if="isReCAPTCHAEnabled" class="text-white"
                              to="https://policies.google.com/terms" target="_blank">Google reCAPTCHA 服务条款</NuxtLink>
                </p>
            </div>
            <footer class="text-center p-3" id="footer-lower">
                <span>{{ config.footerText }}</span>
            </footer>
        </footer>
    </div>
</template>

<script setup lang="ts">
import iconLoadingBlock from 'assets/icon-loading-block.svg';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';

const isRouteUpdating = useRouteUpdatingStore().isUpdating;
const config = useRuntimeConfig().public;
const isReCAPTCHAEnabled = config.recaptchaSiteKey !== '';
const isGoogleAnalyticsEnabled = config.gaMeasurementID !== '';
</script>

<style scoped>
#app-wrapper{
    min-height: 100vh;
}
#footer-upper {
    background-color: #2196f3;
}
#footer-lower {
    background-color: rgba(0,0,0,.2);
}
#loading-block {
    height: 200px;
    margin: auto;
}
</style>
