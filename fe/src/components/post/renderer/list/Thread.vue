<template>
<article class="mt-3 card" :id="`tid/${thread.tid}`">
    <header
        ref="stickyTitleEl"
        :class="{ 'highlight-post': highlightPostStore.isHighlightingPost(thread, 'tid') }"
        class="thread-title shadow-sm card-header sticky-top">
        <div class="thread-title-inline-start row flex-nowrap">
            <div class="thread-title-inline-start-title-wrapper col-auto flex-shrink-1 w-100 h-100 d-flex">
                <PostBadgeThread :thread="thread" />
                <h6 class="thread-title-inline-start-title overflow-hidden text-nowrap">{{ thread.title }}</h6>
            </div>
            <div class="col-auto badge bg-light fs-6 p-1 pt-0 pe-2" role="group">
                <PostBadgeCommon :post="thread" postIDKey="tid" postTypeText="主题帖" />
                <PostBadgeTime
                    postType="主题帖" currentPostIDKey="tid"
                    postTimeKey="postedAt" timestampType="发帖时间"
                    :previousPost="previousThread" :currentPost="thread" :nextPost="nextThread"
                    class="bg-success" />
            </div>
        </div>
        <div class="row justify-content-between mt-2">
            <div class="col-auto d-flex gap-1 align-items-center">
                <span v-tippy="'回复量'" class="badge bg-secondary user-select-all">
                    <FontAwesome :icon="faCommentAlt" class="me-1" />{{ thread.replyCount }}
                </span>
                <span v-tippy="'浏览量'" class="badge bg-info user-select-all">
                    <FontAwesome :icon="faEye" class="me-1" />{{ thread.viewCount }}
                </span>
                <span
                    v-if="thread.shareCount !== 0"
                    v-tippy="'分享量'" class="badge bg-info user-select-all">
                    <FontAwesome :icon="faShareAlt" class="me-1" /> {{ thread.shareCount }}
                </span>
                <span v-tippy="'赞踩量'" class="badge bg-info user-select-all">
                    <FontAwesome :icon="faThumbsUp" class="me-1" /> {{ thread.agreeCount }}
                    <FontAwesome :icon="faThumbsDown" class="me-1" /> {{ thread.disagreeCount }}
                </span>
                <span
                    v-if="thread.zan !== null"
                    v-tippy="zanTippyContent(thread.zan)" class="badge bg-info user-select-all">
                    <FontAwesome :icon="faThumbsUp" class="me-1" /> 旧版客户端赞
                </span>
                <span
                    v-if="thread.geolocation !== null"
                    v-tippy="'发帖位置'" class="badge bg-info user-select-all">
                    <FontAwesome :icon="faLocationArrow" class="me-1" /> {{ thread.geolocation }}
                    <!-- todo: unknown json struct -->
                </span>
            </div>
            <div class="col-auto badge bg-light fs-6 p-1 pe-2" role="group">
                <address class="d-inline fs-.75">
                    <PostBadgeThreadAuthorAndLatestReplier :thread="thread" />
                </address>
                <PostBadgeTime
                    postType="主题帖" currentPostIDKey="tid"
                    postTimeKey="latestReplyPostedAt" timestampType="最后回复时间"
                    :previousPost="previousThread" :currentPost="thread" :nextPost="nextThread"
                    class="bg-secondary" />
            </div>
        </div>
    </header>
    <PostRendererListReply
        v-for="(reply, index) in thread.replies" :key="reply.pid"
        :previousReply="thread.replies[index - 1]" :reply="reply"
        :nextReply="thread.replies[index + 1]" :thread="thread" />
</article>
</template>

<script setup lang="ts">
import { faCommentAlt, faEye, faLocationArrow, faShareAlt, faThumbsDown, faThumbsUp } from '@fortawesome/free-solid-svg-icons';
import { DateTime } from 'luxon';

const props = defineProps<{
    previousThread?: ThreadWithGroupedSubReplies,
    thread: ThreadWithGroupedSubReplies,
    nextThread?: ThreadWithGroupedSubReplies
}>();
const highlightPostStore = useHighlightPostStore();
const { currentCursor } = usePostPageProvision().inject();
const { stickyTitleEl } = useViewportTopmostPostStore()
    .intersectionObserver({ cursor: currentCursor.value, tid: props.thread.tid });

// todo: fetch users info in zan.userIdList
const zanTippyContent = (zan: NonNullable<Thread['zan']>) => () => {
    const latestTimeText = () => {
        const dateTime = DateTime.fromSeconds(Number(zan.lastTime));
        if (useHydrationStore().isHydratingOrSSR) {
            return setDateTimeZoneAndLocale()(dateTime)
                .toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
        }
        const locale = dateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS);
        if (import.meta.server)
            return locale;
        const relative = dateTime.toRelative({ round: false });

        return `
            <span class="user-select-all">${relative}</span>
            <span class="user-select-all">${locale}</span>`;
    };

    return `
        点赞量：<span class="user-select-all">${String(zan.num)}</span><br>
        最后点赞时间：${latestTimeText()}<br>
        近期点赞用户：<span class="user-select-all">${JSON.stringify(zan.userIdList)}</span>`;
};
</script>

<style scoped>
:deep(.highlight-post) {
    background-color: antiquewhite !important;
}

.thread-title {
    block-size: v-bind('replyTitleStyle().insetBlockStart');
    padding: .75rem 1rem .5rem 1rem;
    background-color: #f2f2f2;
}
.thread-title-inline-start {
    max-block-size: 1.6rem;
}
.thread-title-inline-start-title-wrapper {
    padding-block-start: .2rem;
}
.thread-title-inline-start-title {
    text-overflow: ellipsis;
    flex-basis: 100%;
    inline-size: 0;
}

:deep(.fs-\.75) {
    font-size: .75rem;
}
</style>
