<template>
    <GlobalNavBar />
    <HorizontalMobileMessage />
    <img id="loadingBlocksRouteUpdate" src="../public/assets/icon-loading-blocks.svg" class="d-none" />
    <ConfigProvider :locale="AntdZhCn">
        <div class="container">
            <RouterView />
        </div>
        <RouterView name="escapeContainer" />
    </ConfigProvider>
    <footer class="footer-outer text-light pt-4 mt-4">
        <div class="text-center">
            <p>四叶重工QQ群：292311751</p>
            <p>
                Google <a class="text-white" href="https://www.google.com/analytics/terms/cn.html" target="_blank">Analytics 服务条款</a> |
                <a class="text-white" href="https://policies.google.com/terms" target="_blank">reCAPTCHA 服务条款</a> |
                <a class="text-white" href="https://policies.google.com/privacy" target="_blank">隐私条款</a>
            </p>
        </div>
        <footer class="footer-inner text-center p-3">
            <span>© 2018 ~ 2021 n0099</span>
        </footer>
    </footer>
</template>

<script lang="ts">
import GlobalNavBar from '@/components/GlobalNavBar.vue';
import HorizontalMobileMessage from '@/components/HorizontalMobileMessage.vue';
import { defineComponent, onMounted } from 'vue';
import { RouterView } from 'vue-router';
import { ConfigProvider } from 'ant-design-vue';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';

export default defineComponent({
    components: { RouterView, ConfigProvider, GlobalNavBar, HorizontalMobileMessage },
    setup() {
        onMounted(() => { document.getElementById('loadingBlocksInitial')?.remove() });
        return { AntdZhCn };
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
