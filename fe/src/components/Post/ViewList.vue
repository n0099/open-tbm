<template>
    <div :data-page="posts.pages.currentPage" class="post-render-list pb-3">
        <div v-for="thread in posts.threads" :id="`t${thread.tid}`" class="thread-item card">
            <div class="thread-title shadow-sm card-header sticky-top">
                <post-thread-tag :thread="thread"></post-thread-tag>
                <h6 class="d-inline">{{ thread.title }}</h6>
                <div class="float-right badge badge-light">
                    <router-link :to="{ name: 'tid', params: { tid: thread.tid } }" class="badge badge-pill badge-light">只看此贴</router-link>
                    <a :href="$data.$$getTiebaPostLink(thread.tid)" target="_blank" class="badge badge-pill badge-light"><i class="fas fa-link"></i></a>
                    <a :data-tippy-content="`<h6>tid：${thread.tid}</h6><hr />
                                首次收录时间：${moment(thread.created_at).fromNow()}（${thread.created_at}）<br />
                                最后更新时间：${moment(thread.updated_at).fromNow()}（${thread.updated_at}）`"
                       class="badge badge-pill badge-light">
                        <i class="fas fa-info"></i>
                    </a>
                    <span :data-tippy-content="`发帖时间：${thread.postTime}`" class="post-time-badge badge badge-pill badge-success">{{ moment(thread.postTime).fromNow() }}</span>
                </div>
                <div class="mt-1">
                            <span data-tippy-content="回复量" class="badge badge-info">
                                <i class="far fa-comment-alt"></i> {{ thread.replyNum }}
                            </span>
                    <span data-tippy-content="浏览量" class="badge badge-info">
                                <i class="far fa-eye"></i> {{ thread.viewNum }}
                            </span>
                    <span v-if="thread.shareNum !== 0" data-tippy-content="分享量" class="badge badge-info">
                                <i class="fas fa-share-alt"></i> {{ thread.shareNum }}
                            </span>
                    <span v-if="thread.agreeInfo !== null" data-tippy-content="赞踩量" class="badge badge-info">
                                <i class="far fa-thumbs-up"></i> {{ thread.agreeInfo.agree_num }}
                                <i class="far fa-thumbs-down"></i> {{ thread.agreeInfo.disagree_num }}
                            </span>
                    <span v-if="thread.zanInfo !== null" :data-tippy-content="`
                                    点赞量：${thread.zanInfo.num}<br />
                                    最后点赞时间：${moment.unix(thread.zanInfo.last_time).fromNow()}（${moment.unix(thread.zanInfo.last_time).format('YYYY-MM-DD HH:mm:ss')}）<br />
                                    近期点赞用户：${thread.zanInfo.user_id_list}<br />`"
                          class="badge badge-info"><!-- todo: fetch users info in zanInfo.user_id_list -->
                                <i class="far fa-thumbs-up"></i> 旧版客户端赞
                            </span>
                    <span v-if="thread.location !== null" data-tippy-content="发帖位置" class="badge badge-info">
                                <i class="fas fa-location-arrow"></i> {{ thread.location }} <!-- todo: unknown json struct -->
                            </span>
                    <div class="float-right btn-group" role="group">
                        <a :href="$data.$$getTiebaUserLink($data.$getUserInfo(thread.authorUid).name)" target="_blank" class="badge btn btn-light">
                            <span v-if="thread.latestReplierUid !== thread.authorUid" class="font-weight-bold text-success">楼主：</span>
                            <span v-else class="font-weight-bold text-info">楼主及最后回复：</span>
                            <span class="font-weight-normal">{{ renderUsername(thread.authorUid) }}</span>
                        </a>
                        <post-user-tag v-if="thread.authorManagerType !== null" :user-info="{ managerType: thread.authorManagerType }" :users-info-source="posts.users"></post-user-tag>
                        <template v-if="thread.latestReplierUid !== thread.authorUid">
                            <a :href="$data.$$getTiebaUserLink($data.$getUserInfo(thread.latestReplierUid).name)" target="_blank" class="badge btn btn-light">
                                <span class="font-weight-bold text-secondary">最后回复：</span>
                                <span class="font-weight-normal">{{ renderUsername(thread.latestReplierUid) }}</span>
                            </a>
                        </template>
                        <div class="thread-latest-reply-time-badge d-inline badge badge-light">
                            <span :data-tippy-content="`最后回复时间：${thread.latestReplyTime}`" class="post-time-badge badge badge-pill badge-secondary">{{ moment(thread.latestReplyTime).fromNow() }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-for="reply in thread.replies" :id="reply.pid">
                <div class="reply-title sticky-top card-header">
                    <div class="d-inline h5">
                        <span class="badge badge-info">{{ reply.floor }}楼</span>
                        <span v-if="reply.subReplyNum > 0" class="badge badge-info">
                                {{ reply.subReplyNum }}条<i class="far fa-comment-dots"></i>
                            </span>
                        <!-- TODO: implement these reply's property
                            <span>fold:{{ reply.isFold }}</span>
                            <span>{{ reply.agreeInfo }}</span>
                            <span>{{ reply.signInfo }}</span>
                            <span>{{ reply.tailInfo }}</span>
                        -->
                    </div>
                    <div class="float-right badge badge-light">
                        <router-link :to="{ name: 'pid', params: { pid: reply.pid } }" class="badge badge-pill badge-light">只看此楼</router-link>
                        <a :href="$data.$$getTiebaPostLink(reply.tid, reply.pid)" target="_blank" class="badge badge-pill badge-light"><i class="fas fa-link"></i></a>
                        <a :data-tippy-content="`
                                    <h6>pid：${reply.pid}</h6><hr />
                                    首次收录时间：${moment(reply.created_at).fromNow()}（${reply.created_at}）<br />
                                    最后更新时间：${moment(reply.updated_at).fromNow()}（${reply.updated_at}）`"
                           class="badge badge-pill badge-light">
                            <i class="fas fa-info"></i>
                        </a>
                        <span :data-tippy-content="reply.postTime" class="post-time-badge badge badge-pill badge-primary">{{ moment(reply.postTime).fromNow() }}</span>
                    </div>
                </div>
                <div class="reply-info shadow-sm row bs-callout bs-callout-info">
                    <div v-for="author in [$data.$getUserInfo(reply.authorUid)]" class="reply-banner text-center col-auto">
                        <div class="reply-user-info sticky-top shadow-sm badge badge-light">
                            <a :href="$data.$$getTiebaUserLink(author.name)" target="_blank" class="d-block">
                                <img :data-src="$data.$$getTiebaUserAvatarUrl(author.avatarUrl)" class="tieba-user-avatar-large lazyload d-block mx-auto badge badge-light"/>
                                <span>
                                        {{ author.name }}
                                        <br v-if="author.displayName !== null && author.name !== null" />
                                        {{ author.displayName }}
                                    </span>
                            </a>
                            <post-user-tag :user-info="{
                                                    uid: { current: reply.authorUid, thread: thread.authorUid },
                                                    managerType: reply.authorManagerType,
                                                    expGrade: reply.authorExpGrade
                                                   }" :users-info-source="posts.users"></post-user-tag>
                        </div>
                    </div>
                    <div class="reply-body col border-left">
                        <div class="p-2" v-html="reply.content"></div>
                        <template v-if="reply.subReplies.length > 0">
                            <div v-for="subReplyGroup in reply.subReplies" class="sub-reply-group bs-callout bs-callout-success">
                                <ul class="list-group list-group-flush">
                                    <li v-for="(subReply, subReplyIndex) in subReplyGroup" @mouseenter="hoveringSubReplyID = subReply.spid" @mouseleave="hoveringSubReplyID = 0" class="sub-reply-item list-group-item">
                                        <template v-for="author in [$data.$getUserInfo(subReply.authorUid)]">
                                            <a v-if="subReplyGroup[subReplyIndex - 1] === undefined" :href="$data.$$getTiebaUserLink(author.name)" target="_blank" class="sub-reply-user-info badge badge-light">
                                                <img :data-src="$data.$$getTiebaUserAvatarUrl(author.avatarUrl)" class="tieba-user-avatar-small lazyload" />
                                                <span>{{ renderUsername(subReply.authorUid) }}</span>
                                                <post-user-tag :user-info="{
                                                                        uid: { current: subReply.authorUid, thread: thread.authorUid, reply: reply.authorUid },
                                                                        managerType: subReply.authorManagerType,
                                                                        expGrade: subReply.authorExpGrade
                                                                       }" :users-info-source="posts.users"></post-user-tag>
                                            </a>
                                            <div class="float-right badge badge-light">
                                                <div :class="{
                                                                'd-none': hoveringSubReplyID !== subReply.spid,
                                                                'd-inline': hoveringSubReplyID === subReply.spid
                                                             }"><!-- fixme: high cpu usage due to js evaling while quickly emitting hover event -->
                                                    <a :href="$data.$$getTiebaPostLink(subReply.tid, null, subReply.spid)" target="_blank" class="badge badge-pill badge-light"><i class="fas fa-link"></i></a>
                                                    <a :data-tippy-content="`
                                                                <h6>spid：${subReply.spid}</h6><hr />
                                                                首次收录时间：${moment(subReply.created_at).fromNow()}（${subReply.created_at}）<br />
                                                                最后更新时间：${moment(subReply.created_at).fromNow()}（${subReply.updated_at}）`"
                                                       class="badge badge-pill badge-light">
                                                        <i class="fas fa-info"></i>
                                                    </a>
                                                </div>
                                                <span :data-tippy-content="subReply.postTime" class="post-time-badge badge badge-pill badge-info">{{ moment(subReply.postTime).fromNow() }}</span>
                                            </div>
                                        </template>
                                        <div v-html="subReply.content"></div>
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

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

    }
});

const postRenderListComponent = Vue.component('post-render-list', {
    template: '#post-render-list-template',
    props: {
        initialPosts: { type: Object, required: true }
    },
    data () {
        return {
            moment,
            $$getTiebaUserLink,
            $$getTiebaPostLink,
            $$getTiebaUserAvatarUrl,
            $getUserInfo: window.$getUserInfo(this.$props.initialPosts.users),
            hoveringSubReplyID: 0 // for display item's right floating hide buttons
        };
    },
    computed: {
        posts () {
            let postsData = _.cloneDeep(this.$props.initialPosts); // prevent mutates prop in other post renders
            postsData.threads = _.map(postsData.threads, (thread) => {
                thread.replies = _.map(thread.replies, (reply) => {
                    reply.subReplies = _.reduce(reply.subReplies, (groupedSubReplies, subReply, index, subReplies) => {
                        // group sub replies item by continuous and same author info
                        let previousSubReply = subReplies[index - 1];
                        if (previousSubReply !== undefined
                            && subReply.authorUid === previousSubReply.authorUid
                            && subReply.authorManagerType === previousSubReply.authorManagerType
                            && subReply.authorExpGrade === previousSubReply.authorExpGrade) {
                            _.last(groupedSubReplies).push(subReply); // append to last group
                        } else {
                            groupedSubReplies.push([subReply]); // new group
                        }
                        return groupedSubReplies;
                    }, []);
                    return reply;
                });
                return thread;
            });
            return postsData;
        }
    },
    mounted () {
        if (this.$route.hash !== '') {
            $(`.post-render-list[data-page='${this.$route.params.page || 1}'] #${this.$route.hash.substr(1)}`)[0]?.scrollIntoView(); // scroll to route hash determined reply or thread item after initial load
        }
        this.$nextTick(() => { // initial dom event after all dom and child components rendered
            $$registerTippy();
            $$registerTiebaImageZoomEvent();
        });
    },
    beforeDestroy () {
        // this.$el is already unmounted from document while beforeDestroy()
        $$registerTippy(this.$el, true);
        $$registerTiebaImageZoomEvent(this.$el, true);
    },
    methods: {
        renderUsername: function (uid) {
            let user = this.$data.$getUserInfo(uid);
            let name = user.name;
            let displayName = user.displayName;
            if (name === null) {
                return `${displayName !== null ? `${displayName}` : `无用户名或覆盖名（UID：${user.uid}）`}`;
            } else {
                return `${name} ${displayName !== null ? `（${displayName}）` : ''}`;
            }
        }
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
    top: 62px;
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

.post-time-badge {
    padding-left: 1em;
    padding-right: 1em;
}
.thread-latest-reply-time-badge {
    height: 20px;
    padding: 0.2em .4em;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
</style>
