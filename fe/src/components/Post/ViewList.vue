<template>
    <div :data-page="posts.pages.currentPage" class="post-render-list pb-3">
        <div v-for="thread in posts.threads" :key="thread.tid" :id="`t${thread.tid}`" class="thread-item card">
            <div class="thread-title shadow-sm card-header sticky-top">
                <div class="row justify-content-between">
                    <ThreadTag :thread="thread" />
                    <h6 class="col-auto d-inline">{{ thread.title }}</h6>
                    <div class="col-auto badge bg-light">
                        <RouterLink :to="{ name: 'post/tid', params: { tid: thread.tid } }"
                                    class="badge bg-light rounded-pill link-dark">只看此贴</RouterLink>
                        <a :href="tiebaPostLink(thread.tid)" target="_blank"
                           class="badge bg-light rounded-pill link-dark"><FontAwesomeIcon icon="link" size="lg" class="align-bottom" /></a>
                        <a :data-tippy-content="`<h6>tid：${thread.tid}</h6><hr />
                            首次收录时间：${DateTime.fromISO(thread.created_at).toRelative({ round: false })}（${thread.created_at}）<br />
                            最后更新时间：${DateTime.fromISO(thread.updated_at).toRelative({ round: false })}（${thread.updated_at}）`"
                           class="badge bg-light rounded-pill link-dark">
                            <FontAwesomeIcon icon="info" size="lg" class="align-bottom" />
                        </a>
                        <PostTimeBadge :time="thread.postTime" tippy-prefix="发帖时间：" badgeColor="success" class="ms-1" />
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
                            最后点赞时间：${DateTime.fromUnix(thread.zanInfo.last_time).toRelative({ round: false })}
                            （${DateTime.fromUnix(thread.zanInfo.last_time).toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}）<br />
                            近期点赞用户：${thread.zanInfo.user_id_list}<br />`" class="badge bg-info">
                            <!-- todo: fetch users info in zanInfo.user_id_list -->
                            <FontAwesomeIcon icon="thumbs-up" class="me-1" /> 旧版客户端赞
                        </span>
                        <span v-if="thread.location !== null" data-tippy-content="发帖位置" class="badge bg-info">
                            <FontAwesomeIcon icon="location-arrow" class="me-1" /> {{ thread.location }}<!-- todo: unknown json struct -->
                        </span>
                    </div>
                    <div class="col-auto badge bg-light" role="group">
                        <a :href="tiebaUserLink(getUserInfo(thread.authorUid).name)" target="_blank">
                            <span v-if="thread.latestReplierUid !== thread.authorUid"
                                  class="fw-bold link-success">楼主：</span>
                            <span v-else class="fw-bold link-info">楼主及最后回复：</span>
                            <span class="fw-normal link-dark">{{ renderUsername(thread.authorUid) }}</span>
                        </a>
                        <UserTag v-if="thread.authorManagerType !== null"
                                 :user-info="{ managerType: thread.authorManagerType }" :users-info-source="posts.users" />
                        <template v-if="thread.latestReplierUid !== thread.authorUid">
                            <a :href="tiebaUserLink(getUserInfo(thread.latestReplierUid).name)" target="_blank">
                                <span class="fw-bold link-secondary">最后回复：</span>
                                <span class="fw-normal link-dark">{{ renderUsername(thread.latestReplierUid) }}</span>
                            </a>
                        </template>
                        <div class="d-inline pe-0 badge bg-light">
                            <PostTimeBadge :time="thread.latestReplyTime" tippy-prefix="最后回复时间：" badgeColor="secondary" />
                        </div>
                    </div>
                </div>
            </div>
            <div v-for="reply in thread.replies" :key="reply.pid" :id="reply.pid">
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
                        <a :href="tiebaPostLink(reply.tid, reply.pid)" target="_blank"
                           class="badge bg-light rounded-pill link-dark"><FontAwesomeIcon icon="link" size="lg" class="align-bottom" /></a>
                        <a :data-tippy-content="`
                            <h6>pid：${reply.pid}</h6><hr />
                            首次收录时间：${DateTime.fromISO(reply.created_at).toRelative({ round: false })}（${reply.created_at}）<br />
                            最后更新时间：${DateTime.fromISO(reply.updated_at).toRelative({ round: false })}（${reply.updated_at}）`"
                           class="badge bg-light rounded-pill link-dark">
                            <FontAwesomeIcon icon="info" size="lg" class="align-bottom" />
                        </a>
                        <PostTimeBadge :time="reply.postTime" badgeColor="primary" />
                    </div>
                </div>
                <div class="reply-info row shadow-sm bs-callout bs-callout-info">
                    <div v-for="author in [getUserInfo(reply.authorUid)]" :key="author.uid" class="col-auto text-center reply-banner">
                        <div class="reply-user-info sticky-top shadow-sm badge bg-light">
                            <a :href="tiebaUserLink(author.name)" target="_blank" class="d-block">
                                <img :data-src="tiebaUserPortraitUrl(author.avatarUrl)"
                                     class="tieba-user-avatar-large lazyload d-block mx-auto badge bg-light"/>
                                <span>
                                    {{ author.name }}
                                    <br v-if="author.displayName !== null && author.name !== null" />
                                    {{ author.displayName }}
                                </span>
                            </a>
                            <UserTag :user-info="{
                                uid: { current: reply.authorUid, thread: thread.authorUid },
                                managerType: reply.authorManagerType,
                                expGrade: reply.authorExpGrade
                            }" :users-info-source="posts.users" />
                        </div>
                    </div>
                    <div class="reply-body col border-start">
                        <div class="p-2" v-html="reply.content" />
                        <template v-if="reply.subReplies.length > 0">
                            <div v-for="subReplyGroup in reply.subReplies" :key="reply.subReplies.indexOf(subReplyGroup)"
                                 class="sub-reply-group bs-callout bs-callout-success">
                                <ul class="list-group list-group-flush">
                                    <li v-for="(subReply, subReplyIndex) in subReplyGroup" :key="subReply.spid"
                                        @mouseenter="hoveringSubReplyID = subReply.spid"
                                        @mouseleave="hoveringSubReplyID = 0"
                                        class="sub-reply-item list-group-item">
                                        <template v-for="author in [getUserInfo(subReply.authorUid)]" :key="author.uid">
                                            <a v-if="subReplyGroup[subReplyIndex - 1] === undefined"
                                               :href="tiebaUserLink(author.name)"
                                               target="_blank" class="sub-reply-user-info badge bg-light">
                                                <img :data-src="tiebaUserPortraitUrl(author.avatarUrl)" class="tieba-user-avatar-small lazyload" />
                                                <span class="align-middle ms-1 link-dark">{{ renderUsername(subReply.authorUid) }}</span>
                                                <UserTag :user-info="{
                                                    uid: { current: subReply.authorUid, thread: thread.authorUid, reply: reply.authorUid },
                                                    managerType: subReply.authorManagerType,
                                                    expGrade: subReply.authorExpGrade
                                                }" :users-info-source="posts.users" />
                                            </a>
                                            <div class="float-end badge bg-light">
                                                <div :class="{
                                                    'd-none': hoveringSubReplyID !== subReply.spid,
                                                    'd-inline': hoveringSubReplyID === subReply.spid
                                                }"><!-- fixme: high cpu usage due to js evaling while quickly emitting hover event -->
                                                    <a :href="tiebaPostLink(subReply.tid, subReply.spid)" target="_blank"
                                                       class="badge bg-light rounded-pill link-dark"><FontAwesomeIcon icon="link" size="lg" class="align-bottom" /></a>
                                                    <a :data-tippy-content="`
                                                        <h6>spid：${subReply.spid}</h6><hr />
                                                        首次收录时间：${DateTime.fromISO(subReply.created_at).toRelative({ round: false })}（${subReply.created_at}）<br />
                                                        最后更新时间：${DateTime.fromISO(subReply.created_at).toRelative({ round: false })}（${subReply.updated_at}）`"
                                                       class="badge bg-light rounded-pill link-dark">
                                                        <FontAwesomeIcon icon="info" size="lg" class="align-bottom" />
                                                    </a>
                                                </div>
                                                <PostTimeBadge :time="subReply.postTime" badgeColor="info" />
                                            </div>
                                        </template>
                                        <div v-html="subReply.content" />
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
import { tiebaPostLink, tiebaUserLink, tiebaUserPortraitUrl } from '@/shared';
import { initialTippy } from '@/shared/tippy';
import { dateTimeFromUTC8 } from '@/shared/echarts';
import { PostTimeBadge, ThreadTag, UserTag } from './';
import { computed, defineComponent, nextTick, onBeforeUnmount, onMounted, reactive, toRefs } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';
import { DateTime } from 'luxon';

export default defineComponent({
    components: { RouterLink, FontAwesomeIcon, PostTimeBadge, ThreadTag, UserTag },
    props: {
        initialPosts: { type: Object, required: true }
    },
    setup(props) {
        const route = useRoute();
        const state = reactive({
            hoveringSubReplyID: 0 // for display item's right floating hide buttons
        });
        const posts = computed(() => {
            const postsData = _.cloneDeep(props.initialPosts); // prevent mutates prop in other post renders
            postsData.threads = _.map(postsData.threads, thread => {
                thread.replies = _.map(thread.replies, reply => {
                    reply.subReplies = _.reduce(reply.subReplies, (groupedSubReplies, subReply, index, subReplies) => {
                        // group sub replies item by continuous and same author info
                        const previousSubReply = subReplies[index - 1];
                        if (previousSubReply !== undefined
                            && subReply.authorUid === previousSubReply.authorUid
                            && subReply.authorManagerType === previousSubReply.authorManagerType
                            && subReply.authorExpGrade === previousSubReply.authorExpGrade) _.last(groupedSubReplies).push(subReply); // append to last group
                        else groupedSubReplies.push([subReply]); // new group

                        return groupedSubReplies;
                    }, []);
                    return reply;
                });
                return thread;
            });
            return postsData;
        });
        const getUserInfo = /* window.getUserInfo(props.initialPosts.users) */ () => ({
            id: 0,
            uid: 0,
            name: '未知用户',
            displayName: null,
            avatarUrl: null,
            gender: 0,
            fansNickname: null,
            iconInfo: []
        });
        const renderUsername = uid => {
            const user = getUserInfo(uid);
            const { name } = user;
            const { displayName } = user;
            if (name === null) return `${displayName !== null ? displayName : `无用户名或覆盖名（UID：${user.uid}）`}`;
            return `${name} ${displayName !== null ? `（${displayName}）` : ''}`;
        };
        onMounted(initialTippy);

        onMounted(() => {
            if (route.hash !== '') $(`.post-render-list[data-page='${route.params.page || 1}'] #${route.hash.substr(1)}`)[0].scrollIntoView(); // scroll to route hash determined reply or thread item after initial load

            nextTick(() => { // initial dom event after all dom and child components rendered
                $$registerTippy();
                $$registerTiebaImageZoomEvent();
            });
        });
        onBeforeUnmount(() => {
            // this.$el is already unmounted from document while beforeDestroy()
            $$registerTippy(this.$el, true);
            $$registerTiebaImageZoomEvent(this.$el, true);
        });

        return { DateTime, tiebaPostLink, tiebaUserLink, tiebaUserPortraitUrl, dateTimeFromUTC8, ...toRefs(state), posts, renderUsername, getUserInfo };
    }
});
</script>

<style scoped>
/* <post-render-list> and <post-render-table> */
.tieba-user-avatar-small {
    width: 25px;
    height: 25px;
}
.tieba-user-avatar-large {
    width: 90px;
    height: 90px;
}
/* <post-render-list> */
.thread-item {
    margin-top: 1em;
}
.thread-title {
    padding: .75em 1em .5em 1em;
    background-color: #f2f2f2;
}

.reply-title {
    z-index: 1019;
    top: 5rem;
    margin-top: .625em;
    border-top: 1px solid #ededed;
    border-bottom: 0;
    background: linear-gradient(rgba(237,237,237,1), rgba(237,237,237,.1));
}
.reply-info {
    padding: .625em;
    margin: 0;
    border-top: 0;
}
.reply-banner {
    padding-left: 0;
    padding-right: .5em;
}
.reply-body {
    overflow: auto;
    padding-left: .5em;
    padding-right: .5em;
}
.reply-user-info {
    z-index: 1018;
    top: 8em;
    padding: .25em;
    font-size: 1em;
    line-height: 140%;
}

.sub-reply-group {
    margin: .5em 0 .25em .5em;
    padding: .25em;
}
.sub-reply-item {
    padding: .125em;
}
.sub-reply-item > * {
    padding: .25em;
}
.sub-reply-user-info {
    font-size: 0.9em;
}
</style>
