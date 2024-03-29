<template>
    <div :data-post-id="reply.pid" :id="reply.pid.toString()">
        <div :ref="el => elementRefsStore.pushOrClear('<RendererList>.reply-title', el as Element | null)"
             class="reply-title sticky-top card-header">
            <div class="d-inline-flex gap-1 fs-5">
                <span class="badge bg-secondary">{{ reply.floor }}楼</span>
                <span v-if="reply.subReplyCount > 0" class="badge bg-info">
                    {{ reply.subReplyCount }}条<FontAwesomeIcon :icon="faCommentDots" />
                </span>
                <!-- TODO: implement these reply's property
                    <span>fold:{{ reply.isFold }}</span>
                    <span>{{ reply.agree }}</span>
                    <span>{{ reply.sign }}</span>
                    <span>{{ reply.tail }}</span>
                -->
            </div>
            <div class="float-end badge bg-light fs-6 p-1 pe-2" role="group">
                <BadgePostCommon :post="reply" postIDKey="pid" postTypeText="回复贴" />
                <BadgePostTime postType="回复贴" :parentPost="thread"
                               :previousPost="previousReply" :currentPost="reply" :nextPost="nextReply"
                               postTimeKey="postedAt" timestampType="发帖时间" class="bg-primary" />
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
                <BadgeUser :user="getUser(reply.authorUid)" :threadAuthorUid="thread.authorUid" />
            </div>
            <div class="col me-2 px-1 border-start overflow-auto">
                <div v-viewer.static class="reply-content p-2" v-html="reply.content" />
                <template v-if="reply.subReplies.length > 0">
                    <SubReplyGroup v-for="(subReplyGroup, index) in reply.subReplies" :key="index"
                                   :previousSubReplyGroup="reply.subReplies[index - 1]" :subReplyGroup="subReplyGroup"
                                   :nextSubReplyGroup="reply.subReplies[index + 1]" :thread="thread" :reply="reply" />
                </template>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { ThreadWithGroupedSubReplies, UserProvision } from './RendererList.vue';
import { guessReplyContainIntrinsicBlockSize } from './index';
import SubReplyGroup from './SubReplyGroup.vue';
import BadgePostCommon from '@/components/Post/badges/BadgePostCommon.vue';
import BadgePostTime from '@/components/Post/badges/BadgePostTime.vue';
import BadgeUser from '@/components/Post/badges/BadgeUser.vue';
import { toUserPortraitImageUrl, toUserRoute } from '@/shared';
import { useElementRefsStore } from '@/stores/elementRefs';
import '@/styles/bootstrapCallout.css';

import { inject, nextTick, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faCommentDots } from '@fortawesome/free-solid-svg-icons';

type ReplyWithGroupedSubReplies = ThreadWithGroupedSubReplies['replies'][number];
defineProps<{
    thread: ThreadWithGroupedSubReplies,
    previousReply?: ReplyWithGroupedSubReplies,
    reply: ReplyWithGroupedSubReplies,
    nextReply?: ReplyWithGroupedSubReplies
}>();

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
