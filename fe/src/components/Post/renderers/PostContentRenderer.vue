<template>
    <div v-viewer.static>
        <div v-for="(i, index) in content" :key="index" class="post-content-item">
            <NewlineToBr is="span" v-if="i.type === undefined" :text="i.text" />
            <NuxtLink v-if="i.type === 1 || i.type === 18"
               :to="tryExtractTiebaOutboundUrl(i.link)" target="_blank">{{ i.text }}</NuxtLink>
            <img v-if="i.type === 2" :src="emoticonUrl(i.text)" :alt="i.c"
                 referrerpolicy="no-referrer" loading="lazy" />
            <img v-if="i.type === 3" :src="imageUrl(i.originSrc)"
                 referrerpolicy="no-referrer" loading="lazy" class="tieba-ugc-image" />
            <NuxtLink v-if="i.type === 4"
               :to="`https://tieba.baidu.com/home/main?un=${_.trimStart(i.text, '@')}`"
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
            <img v-if="i.type === 11" :src="toHTTPS(i.dynamic)" :alt="i.c"
                 referrerpolicy="no-referrer" loading="lazy" class="d-block" />
            <img v-if="i.type === 16" :src="toHTTPS(i.graffitiInfo?.url)" alt="贴吧涂鸦"
                 referrerpolicy="no-referrer" loading="lazy" class="tieba-ugc-image" />
            <NuxtLink v-if="i.type === 20" :to="i.memeInfo?.detailLink" target="_blank">
                <img :src="toHTTPS(i.src)"
                     referrerpolicy="no-referrer" loading="lazy" class="tieba-ugc-image" />
            </NuxtLink>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { PostContent } from '@/api/postContent';
import { useViewerStore } from '@/stores/viewer';
import _ from 'lodash';

defineProps<{ content: PostContent | null }>();
useViewerStore().enable();

const toHTTPS = (url?: string) => url?.replace('http://', 'https://');
const imageUrl = (originSrc?: string) =>
    (originSrc !== undefined && /^(?:[0-9a-f]{40}|[0-9a-f]{24})$/u.test(originSrc)
        ? `https://imgsrc.baidu.com/forum/pic/item/${originSrc}.jpg`
        : originSrc);
const tryExtractTiebaOutboundUrl = (rawURL?: string) => {
    const url = new URL(rawURL ?? '');
    if (url.hostname === 'tieba.baidu.com' && url.pathname === '/mo/q/checkurl')
        return url.searchParams.get('url') ?? undefined;

    return rawURL;
};
const emoticonUrl = (text?: string) => {
    if (text === undefined)
        return '';
    const regexMatches = /(.+?)(\d+|$)/u.exec(text);
    if (regexMatches === null)
        return '';

    const rawEmoticon = { prefix: regexMatches[1], ordinal: regexMatches[2] };
    if (rawEmoticon.prefix === 'image_emoticon' && rawEmoticon.ordinal === '')
        rawEmoticon.ordinal = '1'; // for tieba hehe emoticon: https://tb2.bdstatic.com/tb/editor/images/client/image_emoticon1.png

    /* eslint-disable @typescript-eslint/naming-convention */
    const emoticonsIndex = {
        image_emoticon: { class: 'client', ext: 'png' }, // 泡泡(<51)/客户端新版表情(>61)
        // image_emoticon: { class: 'face', ext: 'gif', prefix: 'i_f' }, // 旧版泡泡
        'image_emoticon>51': { class: 'face', ext: 'gif', prefix: 'i_f' }, // 泡泡-贴吧十周年(51>=i<=61)
        bearchildren_: { class: 'bearchildren', ext: 'gif' }, // 贴吧熊孩子
        tiexing_: { class: 'tiexing', ext: 'gif' }, // 痒小贱
        ali_: { class: 'ali', ext: 'gif' }, // 阿狸
        llb_: { class: 'luoluobu', ext: 'gif' }, // 罗罗布
        b: { class: 'qpx_n', ext: 'gif' }, // 气泡熊
        xyj_: { class: 'xyj', ext: 'gif' }, // 小幺鸡
        ltn_: { class: 'lt', ext: 'gif' }, // 冷兔
        bfmn_: { class: 'bfmn', ext: 'gif' }, // 白发魔女
        pczxh_: { class: 'zxh', ext: 'gif' }, // 张小盒
        t_: { class: 'tsj', ext: 'gif' }, // 兔斯基
        wdj_: { class: 'wdj', ext: 'png' }, // 豌豆荚
        lxs_: { class: 'lxs', ext: 'gif' }, // 冷先森
        B_: { class: 'bobo', ext: 'gif' }, // 波波
        yz_: { class: 'shadow', ext: 'gif' }, // 影子
        w_: { class: 'ldw', ext: 'gif' }, // 绿豆蛙
        '10th_': { class: '10th', ext: 'gif' } // 贴吧十周年
    } as const;
    /* eslint-enable @typescript-eslint/naming-convention */

    const filledEmoticon = {
        ...rawEmoticon,
        ...rawEmoticon.prefix === 'image_emoticon'
            && Number(rawEmoticon.ordinal) >= 51 && Number(rawEmoticon.ordinal) <= 61
            ? emoticonsIndex['image_emoticon>51']
            : emoticonsIndex[rawEmoticon.prefix as keyof typeof emoticonsIndex]
    };

    return `https://tb2.bdstatic.com/tb/editor/images/${filledEmoticon.class}`
        + `/${filledEmoticon.prefix}${filledEmoticon.ordinal}.${filledEmoticon.ext}`;
};
</script>

<style scoped>
.tieba-ugc-image {
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
