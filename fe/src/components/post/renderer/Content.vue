<template>
<div v-viewer.static>
    <!-- eslint-disable-next-line vue/no-unused-vars -->
    <DefineUGCImage v-slot="{ $slots, src, ...attrs }">
        <NuxtLink v-if="useHydrationStore().isHydratingOrSSR()" :to="src" target="_blank" class="tieba-ugc-image">
            <!-- eslint-disable-next-line vue/no-duplicate-attr-inheritance -->
            <img :src="src" referrerpolicy="no-referrer" loading="lazy" class="tieba-ugc-image" v-bind="attrs" />
        </NuxtLink>
        <!-- eslint-disable-next-line vue/no-duplicate-attr-inheritance -->
        <img v-else :src="src" referrerpolicy="no-referrer" loading="lazy" class="tieba-ugc-image" v-bind="attrs" />
    </DefineUGCImage>
    <div v-for="(i, index) in content" :key="index" class="post-content-item">
        <NewlineToBr is="span" v-if="i.type === undefined" :text="i.text" />
        <NuxtLink
            v-if="i.type === 1 || i.type === 18"
            :to="tryExtractTiebaOutboundUrl(i.link)" target="_blank">{{ i.text }}</NuxtLink>
        <img
            v-if="i.type === 2" :src="emoticonUrl(i.text)" :alt="i.c"
            referrerpolicy="no-referrer" loading="lazy" />
        <ReuseUGCImage v-if="i.type === 3" :src="imageUrl(i.originSrc)" />
        <NuxtLink
            v-if="i.type === 4"
            :to="toUserProfileUrl({ name: _.trimStart(i.text, '@') })"
            target="_blank">{{ i.text }}</NuxtLink>
        <template v-if="i.type === 5">
            <template v-if="i.src !== undefined">
                <!--
                    todo: fix anti hotlinking on domain https://tiebapic.baidu.com and http://tb-video.bdstatic.com/tieba-smallvideo-transcode
                    <video controls :poster="i.src" :src="i.link" />
                -->
                <NuxtLink :to="i.text" target="_blank">贴吧视频播放页</NuxtLink>
            </template>
            <template v-else>
                <NuxtLink :to="i.text" target="_blank">[[外站视频：{{ i.text }}]]</NuxtLink>
            </template>
        </template>
        <br v-if="i.type === 7" />
        <span v-if="i.type === 9">{{ i.text }}</span>
        <span v-if="i.type === 10">
            <!-- TODO: fill with voice player and play source url -->
            [[语音 {{ i.voiceMd5 }} 时长:{{ i.duringTime }}s]]
        </span>
        <img
            v-if="i.type === 11" :src="toHTTPS(i.dynamic)" :alt="i.c"
            referrerpolicy="no-referrer" loading="lazy" class="d-block" />
        <ReuseUGCImage v-if="i.type === 16" :src="toHTTPS(i.graffitiInfo?.url)" alt="贴吧涂鸦" />
        <NuxtLink v-if="i.type === 20" :to="i.memeInfo?.detailLink" target="_blank">
            <ReuseUGCImage v-if="i.type === 20" :src="toHTTPS(i.src)" />
        </NuxtLink>
    </div>
</div>
</template>

<script setup lang="ts">
import _ from 'lodash';

defineProps<{ content: PostContent | null }>();
const [DefineUGCImage, ReuseUGCImage] = createReusableTemplate<{ src?: string }>({ inheritAttrs: false });
useViewerStore().enable();
</script>

<style scoped>
img.tieba-ugc-image {
    max-inline-size: 18.75rem;
    max-block-size: 18.75rem;
    object-fit: contain;
    margin: .25rem;
    cursor: zoom-in;
}

/* https://stackoverflow.com/questions/29732575/how-to-specify-line-breaks-in-a-multi-line-flexbox-layout/29733127#29733127 */
.post-content-item {
    display: contents;
}
/* https://stackoverflow.com/questions/18189147/selecting-an-element-that-doesnt-have-a-child-with-a-certain-class */
.post-content-item:not(:has(.tieba-ugc-image)):has(+ .post-content-item > .tieba-ugc-image)::after,
/* allow mupltie continuous .post-content-item > .tieba-ugc-image to not wrap */
.post-content-item:has(.tieba-ugc-image):not(:has(+ .post-content-item > .tieba-ugc-image))::after {
    content: '';
    display: block;
}
</style>
