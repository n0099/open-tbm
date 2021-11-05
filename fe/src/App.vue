<template>
    <GlobalNavBar />
    <HorizontalMobileMessage />
    <img id="loadingBlocksRouteChange" :src="baseUrl + 'assets/icon-loading-blocks.svg'" class="d-none" />
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
import { ConfigProvider } from 'ant-design-vue';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';
import _ from 'lodash';

export default defineComponent({
    components: { GlobalNavBar, ConfigProvider, HorizontalMobileMessage },
    setup() {
        const baseUrl = process.env.BASE_URL;
        onMounted(() => { document.getElementById('loadingBlocksInitial')?.remove() });
        return { AntdZhCn, baseUrl };
    }
});

const $$registerTippy = (scopedRootDom = 'body', unregister = false) => {
    if (unregister) _.each($(scopedRootDom).find('[data-tippy-content]'), dom => dom._tippy.destroy());
    else tippy($(scopedRootDom).find('[data-tippy-content]').get());
};

const $$baseUrl = '{{ $baseUrl }}';
const $$baseUrlDir = $$baseUrl.substr($$baseUrl.indexOf('/', $$baseUrl.indexOf('://') + 3));

const $$getTiebaPostLink = (tid, pid = null, spid = null) => {
    if (spid !== null) return `https://tieba.baidu.com/p/${tid}?pid=${spid}#${spid}`;
    else if (pid !== null) return `https://tieba.baidu.com/p/${tid}?pid=${pid}#${pid}`;

    return `https://tieba.baidu.com/p/${tid}`;
};
const $$getTBMPostLink = (tid, pid = null, spid = null) => {
    if (spid !== null) return `${$$baseUrl}/post/tid/${tid}`;
    else if (pid !== null) return `${$$baseUrl}/post/pid/${pid}`;

    return `${$$baseUrl}/post/spid/${spid}`;
};
const $$getTBMUserLink = username => `${$$baseUrl}/user/n/${username}`;
</script>

<style scoped>
.footer-outer {
    background-color: #2196f3;
}

.footer-inner {
    background-color: rgba(0,0,0,.2);
}
</style>
