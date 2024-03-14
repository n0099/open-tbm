<template>
    <div :data-post-id="thread.tid" class="mt-3 card" :id="`t${thread.tid}`">
        <div :ref="el => elementRefsStore.pushOrClear('<RendererList>.thread-title', el as Element | null)"
             class="thread-title shadow-sm card-header sticky-top">
            <div class="thread-title-inline-start row flex-nowrap">
                <div class="thread-title-inline-start-title-wrapper col-auto flex-shrink-1 w-100 h-100 d-flex">
                    <BadgeThread :thread="thread" />
                    <h6 class="thread-title-inline-start-title overflow-hidden text-nowrap">{{ thread.title }}</h6>
                </div>
                <div class="col-auto badge bg-light fs-6 p-1 pt-0 pe-2" role="group">
                    <RouterLink :to="{ name: 'post/tid', params: { tid: thread.tid } }"
                                class="badge bg-light rounded-pill link-dark">只看此帖</RouterLink>
                    <BadgePostCommon :post="thread" postIDKey="tid" />
                    <BadgePostTime postType="主题帖" :previousPost="previousThread" :currentPost="thread" :nextPost="nextThread"
                                   postTimeKey="postedAt" timestampType="发帖时间" class="bg-success" />
                </div>
            </div>
            <div class="row justify-content-between mt-2">
                <div class="col-auto d-flex gap-1 align-items-center">
                    <span data-tippy-content="回复量" class="badge bg-secondary">
                        <FontAwesomeIcon icon="comment-alt" class="me-1" />{{ thread.replyCount }}
                    </span>
                    <span data-tippy-content="浏览量" class="badge bg-info">
                        <FontAwesomeIcon icon="eye" class="me-1" />{{ thread.viewCount }}
                    </span>
                    <span v-if="thread.shareCount !== 0" data-tippy-content="分享量" class="badge bg-info">
                        <FontAwesomeIcon icon="share-alt" class="me-1" /> {{ thread.shareCount }}
                    </span>
                    <span data-tippy-content="赞踩量" class="badge bg-info">
                        <FontAwesomeIcon icon="thumbs-up" class="me-1" /> {{ thread.agreeCount }}
                        <FontAwesomeIcon icon="thumbs-down" class="me-1" /> {{ thread.disagreeCount }}
                    </span>
                    <span v-if="thread.zan !== null" :data-tippy-content="`
                        点赞量：${thread.zan.num}<br />
                        最后点赞时间：${DateTime.fromSeconds(Number(thread.zan.last_time))
                    .toRelative({ round: false })}
                        ${DateTime.fromSeconds(Number(thread.zan.last_time))
                    .toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br />
                        近期点赞用户：${thread.zan.user_id_list}<br />`" class="badge bg-info">
                        <!-- todo: fetch users info in zan.user_id_list -->
                        <FontAwesomeIcon icon="thumbs-up" class="me-1" /> 旧版客户端赞
                    </span>
                    <span v-if="thread.geolocation !== null" data-tippy-content="发帖位置" class="badge bg-info">
                        <FontAwesomeIcon icon="location-arrow" class="me-1" /> {{ thread.geolocation }}
                        <!-- todo: unknown json struct -->
                    </span>
                </div>
                <div class="col-auto badge bg-light fs-6 p-1 pe-2" role="group">
                    <RouterLink :to="toUserRoute(thread.authorUid)" class="fs-.75">
                        <span v-if="thread.latestReplierUid !== thread.authorUid"
                              class="fw-normal link-success">楼主：</span>
                        <span v-else class="fw-normal link-info">楼主兼最后回复：</span>
                        <span class="fw-bold link-dark">{{ renderUsername(thread.authorUid) }}</span>
                    </RouterLink>
                    <BadgeUser v-if="getUser(thread.authorUid).currentForumModerator !== null"
                               :user="getUser(thread.authorUid)" class="fs-.75 ms-1" />
                    <template v-if="thread.latestReplierUid === null">
                        <span class="fs-.75">
                            <span class="ms-2 fw-normal link-secondary">最后回复：</span>
                            <span class="fw-bold link-dark">未知用户</span>
                        </span>
                    </template>
                    <template v-else-if="thread.latestReplierUid !== thread.authorUid">
                        <RouterLink :to="toUserRoute(thread.latestReplierUid)" class="fs-.75 ms-2">
                            <span class="ms-2 fw-normal link-secondary">最后回复：</span>
                            <span class="fw-bold link-dark">{{ renderUsername(thread.latestReplierUid) }}</span>
                        </RouterLink>
                        <BadgeUser v-if="getUser(thread.latestReplierUid).currentForumModerator !== null"
                                   :user="getUser(thread.latestReplierUid)" class="fs-.75 ms-1" />
                    </template>
                    <BadgePostTime postType="主题帖" :previousPost="previousThread" :currentPost="thread" :nextPost="nextThread"
                                   postTimeKey="latestReplyPostedAt" timestampType="最后回复时间" class="bg-secondary" />
                </div>
            </div>
        </div>
        <ReplyItem v-for="(reply, index) in thread.replies" :key="reply.pid"
                   :previousReply="thread.replies[index - 1]" :reply="reply"
                   :nextReply="thread.replies[index + 1]" :thread="thread" />
    </div>
</template>

<script setup lang="ts">
import type { ThreadWithGroupedSubReplies, UserProvision } from './RendererList.vue';
import ReplyItem from './ReplyItem.vue';
import BadgePostCommon from '@/components/Post/badges/BadgePostCommon.vue';
import BadgePostTime from '@/components/Post/badges/BadgePostTime.vue';
import BadgeThread from '@/components/Post/badges/BadgeThread.vue';
import BadgeUser from '@/components/Post/badges/BadgeUser.vue';
import { toUserRoute } from '@/shared/index';
import { useElementRefsStore } from '@/stores/elementRefs';
import { inject } from 'vue';
import { RouterLink } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';

defineProps<{
    previousThread?: ThreadWithGroupedSubReplies,
    thread: ThreadWithGroupedSubReplies,
    nextThread?: ThreadWithGroupedSubReplies
}>();
const elementRefsStore = useElementRefsStore();
// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
const { getUser, renderUsername } = inject<UserProvision>('userProvision')!;
</script>

<style scoped>
.thread-title {
    block-size: 5rem; /* sync with .reply-title:inset-block-start */
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
.fs-\.75 {
    font-size: .75rem;
}
</style>
