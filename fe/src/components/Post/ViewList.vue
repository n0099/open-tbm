<template>
    <div :data-page="posts.pages.currentPage" class="post-render-list pb-3">
        <div v-for="thread in posts.threads" :key="thread.tid"
             :id="`t${thread.tid}`" :data-post-id="thread.tid" class="mt-3 card">
            <div class="thread-title shadow-sm card-header sticky-top">
                <div class="row justify-content-between">
                    <div class="col-auto">
                        <ThreadTag :thread="thread" />
                        <h6 class="d-inline">{{ thread.title }}</h6>
                    </div>
                    <div class="col-auto badge bg-light">
                        <RouterLink :to="{ name: 'post/tid', params: { tid: thread.tid } }"
                                    class="badge bg-light rounded-pill link-dark">只看此帖</RouterLink>
                        <PostCommonMetadataIconLinks :meta="thread" postTypeID="tid" />
                        <PostTimeBadge :time="thread.postTime" tippyPrefix="发帖时间：" badgeColor="success" />
                    </div>
                </div>
                <div class="row justify-content-between mt-2">
                    <div class="col-auto d-flex gap-1 align-items-center">
                        <span data-tippy-content="回复量" class="badge bg-secondary">
                            <FontAwesomeIcon icon="comment-alt" class="me-1" />{{ thread.replyNum }}
                        </span>
                        <span data-tippy-content="浏览量" class="badge bg-info">
                            <FontAwesomeIcon icon="eye" class="me-1" />{{ thread.viewNum }}
                        </span>
                        <span v-if="thread.shareNum !== 0" data-tippy-content="分享量" class="badge bg-info">
                            <FontAwesomeIcon icon="share-alt" class="me-1" /> {{ thread.shareNum }}
                        </span>
                        <span v-if="thread.agreeInfo !== null" data-tippy-content="赞踩量" class="badge bg-info">
                            <FontAwesomeIcon icon="thumbs-up" class="me-1" /> {{ thread.agreeInfo.agree_num }}
                            <FontAwesomeIcon icon="thumbs-down" class="me-1" /> {{ thread.agreeInfo.disagree_num }}
                        </span>
                        <span v-if="thread.zanInfo !== null" :data-tippy-content="`
                            点赞量：${thread.zanInfo.num}<br />
                            最后点赞时间：${DateTime.fromSeconds(Number(thread.zanInfo.last_time)).toRelative({ round: false })}
                            （${DateTime.fromSeconds(Number(thread.zanInfo.last_time)).toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}）<br />
                            近期点赞用户：${thread.zanInfo.user_id_list}<br />`" class="badge bg-info">
                            <!-- todo: fetch users info in zanInfo.user_id_list -->
                            <FontAwesomeIcon icon="thumbs-up" class="me-1" /> 旧版客户端赞
                        </span>
                        <span v-if="thread.location !== null" data-tippy-content="发帖位置" class="badge bg-info">
                            <FontAwesomeIcon icon="location-arrow" class="me-1" /> {{ thread.location }}<!-- todo: unknown json struct -->
                        </span>
                    </div>
                    <div class="col-auto badge bg-light" role="group">
                        <RouterLink :to="userRoute(thread.authorUid)" target="_blank">
                            <span v-if="thread.latestReplierUid !== thread.authorUid"
                                  class="fw-normal link-success">楼主：</span>
                            <span v-else class="fw-normal link-info">楼主及最后回复：</span>
                            <span class="fw-bold link-dark">{{ renderUsername(thread.authorUid) }}</span>
                        </RouterLink>
                        <UserTag v-if="thread.authorManagerType !== null"
                                 :user="{ managerType: thread.authorManagerType }" />
                        <template v-if="thread.latestReplierUid !== thread.authorUid">
                            <RouterLink :to="userRoute(thread.latestReplierUid)" target="_blank" class="ms-2">
                                <span class="fw-normal link-secondary">最后回复：</span>
                                <span class="fw-bold link-dark">{{ renderUsername(thread.latestReplierUid) }}</span>
                            </RouterLink>
                        </template>
                        <PostTimeBadge :time="thread.latestReplyTime" tippyPrefix="最后回复时间：" badgeColor="secondary" />
                    </div>
                </div>
            </div>
            <div v-for="reply in thread.replies" :key="reply.pid" :id="reply.pid" :data-post-id="reply.pid">
                <div class="reply-title sticky-top card-header">
                    <div class="d-inline-flex gap-1 h5">
                        <span class="badge bg-secondary">{{ reply.floor }}楼</span>
                        <span v-if="reply.subReplyNum > 0" class="badge bg-info">
                            {{ reply.subReplyNum }}条<FontAwesomeIcon icon="comment-dots" />
                        </span>
                        <!-- TODO: implement these reply's property
                            <span>fold:{{ reply.isFold }}</span>
                            <span>{{ reply.agreeInfo }}</span>
                            <span>{{ reply.signInfo }}</span>
                            <span>{{ reply.tailInfo }}</span>
                        -->
                    </div>
                    <div class="float-end badge bg-light">
                        <RouterLink :to="{ name: 'post/pid', params: { pid: reply.pid } }"
                                    class="badge bg-light rounded-pill link-dark">只看此楼</RouterLink>
                        <PostCommonMetadataIconLinks :meta="reply" postTypeID="pid" />
                        <PostTimeBadge :time="reply.postTime" badgeColor="primary" />
                    </div>
                </div>
                <div class="reply-info row shadow-sm bs-callout bs-callout-info">
                    <div v-for="author in [getUser(reply.authorUid)]" :key="author.uid"
                         class="reply-user-info col-auto text-center sticky-top shadow-sm badge bg-light">
                        <RouterLink :to="userRoute(author.uid)" target="_blank" class="d-block">
                            <img :data-src="tiebaUserPortraitUrl(author.avatarUrl)"
                                 class="tieba-user-portrait-large lazy" />
                            <p class="my-0">{{ author.name }}</p>
                            <p v-if="author.displayName !== null && author.name !== null">{{ author.displayName }}</p>
                        </RouterLink>
                        <UserTag :user="{
                            uid: { current: reply.authorUid, thread: thread.authorUid },
                            managerType: reply.authorManagerType,
                            expGrade: reply.authorExpGrade
                        }" />
                    </div>
                    <div class="col me-2 px-1 border-start overflow-auto">
                        <div v-viewer.static class="p-2" v-html="reply.content" />
                        <template v-if="reply.subReplies.length > 0">
                            <div v-for="(subReplyGroup, _k) in reply.subReplies" :key="_k"
                                 class="sub-reply-group bs-callout bs-callout-success">
                                <ul class="list-group list-group-flush">
                                    <li v-for="(subReply, subReplyIndex) in subReplyGroup" :key="subReply.spid"
                                        @mouseenter="hoveringSubReplyID = subReply.spid"
                                        @mouseleave="hoveringSubReplyID = 0"
                                        class="sub-reply-item list-group-item">
                                        <template v-for="author in [getUser(subReply.authorUid)]" :key="author.uid">
                                            <RouterLink v-if="subReplyGroup[subReplyIndex - 1] === undefined"
                                                        :to="userRoute(author.uid)" target="_blank"
                                                        class="sub-reply-user-info text-wrap badge bg-light">
                                                <img :data-src="tiebaUserPortraitUrl(author.avatarUrl)" class="tieba-user-portrait-small lazy" />
                                                <span class="mx-2 align-middle link-dark">{{ renderUsername(subReply.authorUid) }}</span>
                                                <UserTag :user="{
                                                    uid: { current: subReply.authorUid, thread: thread.authorUid, reply: reply.authorUid },
                                                    managerType: subReply.authorManagerType,
                                                    expGrade: subReply.authorExpGrade
                                                }" />
                                            </RouterLink>
                                            <div class="float-end badge bg-light">
                                                <div class="d-inline" :class="{ 'invisible': hoveringSubReplyID !== subReply.spid }">
                                                    <PostCommonMetadataIconLinks :meta="subReply" postTypeID="spid" />
                                                </div>
                                                <PostTimeBadge :time="subReply.postTime" badgeColor="info" />
                                            </div>
                                        </template>
                                        <div v-viewer.static v-html="subReply.content" />
                                    </li>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import '@/shared/bootstrapCallout.css';
import { PostCommonMetadataIconLinks, PostTimeBadge, ThreadTag, UserTag } from './';
import { baseGetUser, baseRenderUsername } from './viewListAndTableCommon';
import { compareRouteIsNewQuery, routePageParamNullSafe, setComponentCustomScrollBehaviour } from '@/router';
import type { ApiPostsQuery, BaiduUserID, ReplyRecord, SubReplyRecord, ThreadRecord } from '@/api/index.d';
import type { Modify } from '@/shared';
import { tiebaUserPortraitUrl } from '@/shared';
import { initialTippy } from '@/shared/tippy';

import type { PropType } from 'vue';
import { computed, defineComponent, onMounted, ref } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { RouterLink } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';
import { DateTime } from 'luxon';

export const postListItemScrollPosition = (route: RouteLocationNormalizedLoaded): { el: string, top: number } => {
    const hash = route.hash.substring(1);
    const idSelectorToHash = _.isEmpty(hash) ? '' : ` [id='${hash}']`;
    return { // https://stackoverflow.com/questions/37270787/uncaught-syntaxerror-failed-to-execute-queryselector-on-document
        el: `.post-render-list[data-page='${routePageParamNullSafe(route)}']${idSelectorToHash}`,
        top: 80 // .reply-title { top: 5rem; }
    };
};
export const isRouteUpdateTriggeredByPostsNavScrollEvent = ref(false);

export default defineComponent({
    components: { RouterLink, FontAwesomeIcon, PostCommonMetadataIconLinks, PostTimeBadge, ThreadTag, UserTag },
    props: {
        initialPosts: { type: Object as PropType<ApiPostsQuery>, required: true }
    },
    setup(props) {
        const hoveringSubReplyID = ref(0);
        const posts = computed(() => {
            const newPosts = props.initialPosts as Modify<ApiPostsQuery, { // https://github.com/microsoft/TypeScript/issues/33591
                threads: Array<ThreadRecord & { replies: Array<ReplyRecord & { subReplies: Array<SubReplyRecord | SubReplyRecord[]> }> }>
            }>;
            newPosts.threads = newPosts.threads.map(thread => {
                thread.replies = thread.replies.map(reply => {
                    reply.subReplies = reply.subReplies.reduce<SubReplyRecord[][]>(
                        (groupedSubReplies, subReply, index, subReplies) => {
                            if (_.isArray(subReply)) return [subReply]; // useless since subReply will never be an array
                            // group sub replies item by continuous and same author info
                            const previousSubReply = subReplies[index - 1] as SubReplyRecord;
                            // https://github.com/microsoft/TypeScript/issues/13778
                            // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
                            if (previousSubReply !== undefined
                                && subReply.authorUid === previousSubReply.authorUid
                                && subReply.authorManagerType === previousSubReply.authorManagerType
                                && subReply.authorExpGrade === previousSubReply.authorExpGrade
                            ) _.last(groupedSubReplies)?.push(subReply); // append to last group
                            else groupedSubReplies.push([subReply]); // new group
                            return groupedSubReplies;
                        },
                        []
                    );
                    return reply;
                });
                return thread;
            });
            return newPosts as Modify<ApiPostsQuery, {
                threads: Array<ThreadRecord & { replies: Array<ReplyRecord & { subReplies: SubReplyRecord[][] }> }>
            }>;
        });
        const getUser = baseGetUser(props.initialPosts.users);
        const renderUsername = baseRenderUsername(getUser);
        const userRoute = (uid: BaiduUserID) => ({ name: 'user/uid', params: { uid } });

        onMounted(initialTippy);
        setComponentCustomScrollBehaviour((to, from) => {
            if (isRouteUpdateTriggeredByPostsNavScrollEvent.value) {
                isRouteUpdateTriggeredByPostsNavScrollEvent.value = false;
                return false;
            }
            if (!compareRouteIsNewQuery(to, from)) return postListItemScrollPosition(to);
            return undefined;
        });

        return { DateTime, tiebaUserPortraitUrl, hoveringSubReplyID, posts, getUser, renderUsername, userRoute };
    }
});
</script>

<style scoped>
.thread-title {
    padding: .75rem 1rem .5rem 1rem;
    background-color: #f2f2f2;
}

.reply-title {
    z-index: 1019;
    top: 5rem;
    margin-top: .625rem;
    border-top: 1px solid #ededed;
    border-bottom: 0;
    background: linear-gradient(rgba(237,237,237,1), rgba(237,237,237,.1));
}
.reply-info {
    padding: .625rem;
    margin: 0;
    border-top: 0;
}
.reply-user-info {
    z-index: 1018;
    top: 8rem;
    padding: .25rem;
    font-size: 1rem;
    line-height: 150%;
}

.sub-reply-group {
    margin: .5rem 0 .25rem .5rem;
    padding: .25rem;
}
.sub-reply-item {
    padding: .125rem;
}
.sub-reply-item > * {
    padding: .25rem;
}
.sub-reply-user-info {
    font-size: .9rem;
}
</style>
