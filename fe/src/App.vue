<template>
    <GlobalNavBar />
    <HorizontalMobileMessage />
    <img id="loadingBlocks" :src="iconLoadingBlocks" class="d-none" />
    <ConfigProvider :locale="AntdZhCn">
        <div class="container">
            <RouterView />
        </div>
        <RouterView name="escapeContainer" />
    </ConfigProvider>
    <footer class="footer-outer text-light pt-4 mt-4">
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
            <span>{{ envFooterText }}</span>
        </footer>
    </footer>
</template>

<script setup lang="ts">
import iconLoadingBlocks from '/assets/icon-loading-blocks.svg';
import GlobalNavBar from '@/components/GlobalNavBar.vue';
import HorizontalMobileMessage from '@/components/HorizontalMobileMessage.vue';

import { RouterView } from 'vue-router';
import { ConfigProvider } from 'ant-design-vue';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';

const envFooterText = import.meta.env.VITE_FOOTER_TEXT;
const isReCAPTCHAEnabled = import.meta.env.VITE_RECAPTCHA_SITE_KEY !== '';
const isGoogleAnalyticsEnabled = import.meta.env.VITE_GA_MEASUREMENT_ID !== '';
</script>

<style scoped>
.footer-outer {
    background-color: #2196f3;
}

.footer-inner {
    background-color: rgba(0,0,0,.2);
}
</style>
