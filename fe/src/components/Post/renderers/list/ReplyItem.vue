<template>
    <div :data-post-id="reply.pid" :id="reply.pid.toString()">
        <div :ref="el => elementRefsStore.pushOrClear('<RendererList>.reply-title', el as Element | null)"
             class="reply-title sticky-top card-header">
            <div class="d-inline-flex gap-1 h5">
                <span class="badge bg-secondary">{{ reply.floor }}楼</span>
                <span v-if="reply.subReplyCount > 0" class="badge bg-info">
                    {{ reply.subReplyCount }}条<FontAwesomeIcon icon="comment-dots" />
                </span>
                <!-- TODO: implement these reply's property
                    <span>fold:{{ reply.isFold }}</span>
                    <span>{{ reply.agree }}</span>
                    <span>{{ reply.sign }}</span>
                    <span>{{ reply.tail }}</span>
                -->
            </div>
            <div class="float-end badge bg-light">
                <RouterLink :to="{ name: 'post/pid', params: { pid: reply.pid } }"
                            class="badge bg-light rounded-pill link-dark">只看此楼</RouterLink>
                <PostCommonMetadataIconLinks :post="reply" postTypeID="pid" :postIDSelector="() => reply.pid" />
                <BadgePostTime :time="reply.postedAt" badgeColor="primary" />
            </div>
        </div>
        <div :ref="el => el !== null && replyElements.push(el as HTMLElement)"
             class="reply row shadow-sm bs-callout bs-callout-info">
            <div v-for="author in [getUser(reply.authorUid)]" :key="author.uid"
                 class="reply-author col-auto text-center sticky-top shadow-sm badge bg-light">
                <RouterLink :to="toUserRoute(author.uid)" class="d-block">
                    <img :src="toUserPortraitImageUrl(author.portrait)" loading="lazy" class="tieba-user-portrait-large" />
                    <p class="my-0">{{ author.name }}</p>
                    <p v-if="author.displayName !== null && author.name !== null">{{ author.displayName }}</p>
                </RouterLink>
                <BadgeUser :user="getUser(reply.authorUid)" :threadAuthorUid="threadAuthorUid" />
            </div>
            <div class="col me-2 px-1 border-start overflow-auto">
                <div v-viewer.static class="reply-content p-2" v-html="reply.content" />
                <template v-if="reply.subReplies.length > 0">
                    <SubReplyGroup v-for="(subReplyGroup, _k) in reply.subReplies" :key="_k" :subReplyGroup="subReplyGroup"
                                   :threadAuthorUid="threadAuthorUid" :replyAuthorUid="reply.authorUid" />
                </template>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { ThreadWithGroupedSubReplies, UserProvision } from './RendererList.vue';
import { guessReplyContainIntrinsicBlockSize } from './index';
import SubReplyGroup from './SubReplyGroup.vue';
import BadgePostTime from '@/components/Post/badges/BadgePostTime.vue';
import BadgeUser from '@/components/Post/badges/BadgeUser.vue';
import PostCommonMetadataIconLinks from '@/components/Post/badges/PostCommonMetadataIconLinks.vue';
import type { BaiduUserID } from '@/api/user';
import { toUserPortraitImageUrl, toUserRoute } from '@/shared';
import { useElementRefsStore } from '@/stores/elementRefs';
import '@/styles/bootstrapCallout.css';
import { inject, nextTick, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

defineProps<{ reply: ThreadWithGroupedSubReplies['replies'][number], threadAuthorUid: BaiduUserID }>();
const elementRefsStore = useElementRefsStore();
// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
const { getUser } = inject<UserProvision>('userProvision')!;
const replyElements = ref<HTMLElement[]>([]);

onMounted(async () => {
    await nextTick();
    guessReplyContainIntrinsicBlockSize(replyElements.value);
});
</script>

<style scoped>
.reply-title {
    z-index: 1019;
    inset-block-start: 5rem;
    margin-block-start: .625rem;
    border-block-start: 1px solid #ededed;
    border-block-end: 0;
    background: linear-gradient(rgba(237,237,237,1), rgba(237,237,237,.1));
}
.reply {
    padding: .625rem;
    border-block-start: 0;
    content-visibility: auto;
    --sub-reply-group-count: 0;
    --predicted-image-height: 0px;
    --predicted-reply-content-height: 0px;
    --predicted-sub-reply-content-height: 0px;
    contain-intrinsic-block-size: auto max(11rem, (var(--sub-reply-group-count) * 4rem) + var(--predicted-image-height)
        + var(--predicted-reply-content-height) + var(--predicted-sub-reply-content-height));
}
.reply-author {
    z-index: 1018;
    inset-block-start: 8rem;
    padding: .25rem;
    font-size: 1rem;
    line-height: 150%;
}
</style>
