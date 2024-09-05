<template>
<article :id="`pid/${reply.pid}`">
    <header
        ref="stickyTitleEl"
        :class="{ 'highlight-post': highlightPostStore.isHighlightingPost(reply, 'pid') }"
        class="reply-title sticky-top card-header">
        <div class="d-inline-flex gap-1 fs-5">
            <span class="badge bg-secondary user-select-all">{{ reply.floor }}楼</span>
            <span v-if="reply.subReplyCount > 0" class="badge bg-info user-select-all">
                {{ reply.subReplyCount }}条<FontAwesome :icon="faCommentDots" />
            </span>
            <!-- TODO: implement these reply's property
                <span>fold:{{ reply.isFold }}</span>
                <span>{{ reply.agree }}</span>
                <span>{{ reply.sign }}</span>
                <span>{{ reply.tail }}</span>
            -->
        </div>
        <div class="float-end badge bg-light fs-6 p-1 pe-2" role="group">
            <PostBadgeCommon :post="reply" postIDKey="pid" postTypeText="回复贴" />
            <PostBadgeTime
                postType="回复贴"
                currentPostIDKey="pid" parentPostIDKey="tid" :parentPost="thread"
                postTimeKey="postedAt" timestampType="发帖时间"
                :previousPost="previousReply" :currentPost="reply" :nextPost="nextReply"
                class="bg-primary" />
        </div>
    </header>
    <div
        :ref="replyElementRefs.set"
        class="reply row shadow-sm bs-callout bs-callout-info">
        <address
            v-for="author in [getUser(reply.authorUid)]" :key="author.uid"
            class="reply-author col-auto h-100 text-center sticky-top shadow-sm badge bg-light">
            <NuxtLink :to="toUserRoute(author.uid)" noPrefetch class="d-block">
                <img :src="toUserPortraitImageUrl(author.portrait)" loading="lazy" class="tieba-user-portrait-large" />
                <p v-if="author.name !== null" class="mb-0">{{ author.name }}</p>
                <p v-if="author.displayName !== null">{{ author.displayName }}</p>
            </NuxtLink>
            <PostBadgeUser :user="getUser(reply.authorUid)" :threadAuthorUid="thread.authorUid" />
        </address>
        <div class="col me-2 px-1 border-start overflow-auto">
            <PostRendererContent :content="reply.content" class="reply-content p-2" />
            <template v-if="reply.subReplies.length > 0">
                <PostRendererListSubReply
                    v-for="(subReplyGroup, index) in reply.subReplies" :key="index"
                    :previousSubReplyGroup="reply.subReplies[index - 1]" :subReplyGroup="subReplyGroup"
                    :nextSubReplyGroup="reply.subReplies[index + 1]" :thread="thread" :reply="reply" />
            </template>
        </div>
    </div>
</article>
</template>

<script setup lang="ts">
import 'assets/css/bootstrapCallout.css';
import type { TemplateRefsList } from '@vueuse/core';
import { faCommentDots } from '@fortawesome/free-solid-svg-icons';

type ReplyWithGroupedSubReplies = ThreadWithGroupedSubReplies['replies'][number];
const props = defineProps<{
    thread: ThreadWithGroupedSubReplies,
    previousReply?: ReplyWithGroupedSubReplies,
    reply: ReplyWithGroupedSubReplies,
    nextReply?: ReplyWithGroupedSubReplies,
    replyElementRefs: TemplateRefsList<HTMLElement>
}>();
const highlightPostStore = useHighlightPostStore();
const { getUser, currentCursor } = usePostPageProvision().inject();
const { stickyTitleEl } = useViewportTopmostPostStore().intersectionObserver(
    { cursor: currentCursor.value, tid: props.reply.tid, pid: props.reply.pid },
    replyTitleStyle().top()
);
</script>

<style scoped>
.reply-title {
    z-index: 1019;
    inset-block-start: v-bind('replyTitleStyle().insetBlockStart');
    margin-block-start: v-bind('replyTitleStyle().marginBlockStart');
    border-block-start: 1px solid #ededed;
    border-block-end: 0;
    background: linear-gradient(rgba(237, 237, 237, 1), rgba(237, 237, 237, .1));
}
.reply-title.highlight-post {
    background-image: none !important;
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
