<template>
    <Meta charset="utf-8" />
    <Meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
    <Link rel="preload" href="assets/icon-loading-block.svg" as="image" />
    <Style>
        .grecaptcha-badge {
            visibility: hidden;
        }
        #loadingBlock {
            height: 200px;
            margin: auto;
        }
        #app {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        body {
            margin: 0;
        }
    </Style>
    <VueQueryDevtools />
    <GlobalNavBar />
    <MinimumResolutionWarning />
    <img :src="iconLoadingBlock" class="d-none" id="loadingBlock" />
    <ConfigProvider :locale="AntdZhCn">
        <div class="container">
            <NuxtPage />
        </div>
        <NuxtPage name="escapeContainer" />
    </ConfigProvider>
    <footer class="footer-outer text-light pt-4 mt-auto">
        <div class="text-center">
            <p>
                <span v-if="isGoogleAnalyticsEnabled">
                    Google <a class="text-white"
                              href="https://www.google.com/analytics/terms/cn.html"
                              target="_blank">Analytics 服务条款</a> |
                    <a class="text-white"
                       href="https://policies.google.com/privacy" target="_blank">Analytics 隐私条款</a>
                </span>
                <span v-if="isReCAPTCHAEnabled && isGoogleAnalyticsEnabled"> | </span>
                <a v-if="isReCAPTCHAEnabled" class="text-white"
                   href="https://policies.google.com/terms" target="_blank">Google reCAPTCHA 服务条款</a>
            </p>
        </div>
        <footer class="footer-inner text-center p-3">
            <span>{{ config.footerText }}</span>
        </footer>
    </footer>
</template>

<script setup lang="ts">
import iconLoadingBlock from 'assets/icon-loading-block.svg';
import { ConfigProvider } from 'ant-design-vue';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';
import { VueQueryDevtools } from '@tanstack/vue-query-devtools';

const config = useRuntimeConfig().public;
const isReCAPTCHAEnabled = config.recaptchaSiteKey !== '';
const isGoogleAnalyticsEnabled = config.gaMeasurementID !== '';
useHead({
    titleTemplate: title => {
        const suffix = `open-tbm @ ${config.instanceName}`;
        return title ? `${title} - ${suffix}` : suffix;
    }
});
</script>

<style scoped>
.footer-outer {
    background-color: #2196f3;
}

.footer-inner {
    background-color: rgba(0,0,0,.2);
}
</style>
