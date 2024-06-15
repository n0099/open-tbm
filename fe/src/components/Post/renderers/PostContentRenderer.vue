<template>
    <div v-viewer.static>
        <template v-for="(item, index) in content">
            <NewlineToBr is="span" :key="index" v-if="item.type === undefined" :text="item.text" />
            <a :key="index" v-if="item.type === 1"
               :href="tryExtractTiebaOutboundUrl(item.link)" target="_blank">{{ item.text }}</a>
        </template>
    </div>
</template>

<script setup lang="ts">
import type { PostContent } from '@/api/postContent';
import NewlineToBr from '@/components/NewlineToBr';

defineProps<{ content: PostContent }>();

const tiebaOutboundUrlRegex = /^http:\/\/tieba\.baidu\.com\/mo\/q\/checkurl\?url=(.+?)(&|$)/u;
const tryExtractTiebaOutboundUrl = (url?: string) =>
    (url === undefined ? undefined : tiebaOutboundUrlRegex.exec(url)?.groups?.[0] ?? url);

</script>

<style scoped>
.tieba-image {
    max-inline-size: 18.75rem;
    max-block-size: 18.75rem;
    object-fit: contain;
    margin: .25rem;
    cursor: zoom-in;
}
span:has(+ .tieba-image) {
    display: block;
}
.tieba-image + span {
    display: block;
}
</style>
