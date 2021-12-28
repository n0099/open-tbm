<template>
    <div class="container-flow">
        <Table :columns="threadColumns" :dataSource="threads" :defaultExpandAllRows="true"
               :expandRowByClick="true" :pagination="false" :scroll="{ x: true }"
               size="middle" class="render-table-thread">
            <template v-slot:tid="text, record" >
                <RouterLink :to="{ name: 'tid', params: { tid: record.tid } }">{{ record.tid }}</RouterLink>
            </template>
            <template v-slot:firstPid="text, record" >
                <RouterLink :to="{ name: 'pid', params: { pid: record.firstPid } }">{{ record.firstPid }}</RouterLink>
            </template>
            <template v-slot:titleWithTag="text, record" >
                <ThreadTag :thread="record" />
                <span>{{ record.title }}</span>
            </template>
            <template v-slot:authorInfo="text, record" >
                <a :href="$$getTiebaUserLink($getUserInfo(record.authorUid).name)" target="_blank">
                    <img :data-src="$$getTiebaUserAvatarUrl($getUserInfo(record.authorUid).avatarUrl)"
                         class="tieba-user-avatar-small lazyload" /> {{ renderUsername(record.authorUid) }}
                </a>
                <UserTag :user="{ managerType: record.authorManagerType }"/>
            </template>
            <template v-slot:latestReplierInfo="text, record" >
                <a :href="$$getTiebaUserLink($getUserInfo(record.latestReplierUid).name)" target="_blank">
                    <img :data-src="$$getTiebaUserAvatarUrl($getUserInfo(record.latestReplierUid).avatarUrl)"
                         class="tieba-user-avatar-small lazyload" /> {{ renderUsername(record.latestReplierUid) }}
                </a>
            </template>
            <template v-slot:expandedRowRender="record" >
                <span v-if="threadsReply[record.tid] === undefined">无子回复帖</span>
                <Table v-else :columns="replyColumns" :dataSource="threadsReply[record.tid]"
                       :defaultExpandAllRows="true" :expandRowByClick="true" :pagination="false" size="middle">
                    <template v-for="thread in [record]" slot="authorInfo" slot-scope="text, record">
                        <a :href="$$getTiebaUserLink($getUserInfo(record.authorUid).name)" target="_blank">
                            <img :data-src="$$getTiebaUserAvatarUrl($getUserInfo(record.authorUid).avatarUrl)"
                                 class="tieba-user-avatar-small lazyload"/> {{ renderUsername(record.authorUid) }}
                        </a>
                        <UserTag :user="{
                            uid: { current: record.authorUid, thread: thread.authorUid },
                            managerType: record.authorManagerType,
                            expGrade: record.authorExpGrade
                        }"/>
                    </template>
                    <template v-slot:expandedRowRender="record" >
                        <div :is="repliesSubReply[record.pid] === undefined ? 'span' : 'p'" v-html="record.content" />
                        <Table v-if="repliesSubReply[record.pid] !== undefined"
                               :columns="subReplyColumns" :dataSource="repliesSubReply[record.pid]"
                               :defaultExpandAllRows="true" :expandRowByClick="true" :pagination="false" size="middle">
                            <template v-for="reply in [record]" slot="authorInfo" slot-scope="text, record">
                                <a :href="$$getTiebaUserLink($getUserInfo(record.authorUid).name)" target="_blank">
                                    <img :data-src="$$getTiebaUserAvatarUrl($getUserInfo(record.authorUid).avatarUrl)"
                                         class="tieba-user-avatar-small lazyload" /> {{ renderUsername(record.authorUid) }}
                                </a>
                                <UserTag :user="{
                                    uid: {
                                        current: record.authorUid,
                                        thread: _.find(posts.threads, { tid: reply.tid }).authorUid,
                                        reply: reply.authorUid
                                    },
                                    managerType: record.authorManagerType,
                                    expGrade: record.authorExpGrade
                                }"/>
                            </template>
                            <template v-slot:expandedRowRender="record" >
                                <span v-html="record.content" />
                            </template>
                        </Table>
                    </template>
                </Table>
            </template>
        </Table>
    </div>
</template>

<script lang="ts">
import { ThreadTag, UserTag } from './';
import { defineComponent, onMounted, reactive, toRefs } from 'vue';
import { RouterLink } from 'vue-router';
import { Table } from 'ant-design-vue';
import _ from 'lodash';

export default defineComponent({
    components: { RouterLink, Table, ThreadTag, UserTag },
    props: {
        posts: { type: Object, required: true }
    },
    setup(props) {
        const state = reactive({
            $$getTiebaUserLink,
            $$getTiebaUserAvatarUrl,
            $getUserInfo: window.$getUserInfo(props.posts.users),
            threads: [],
            threadsReply: [],
            repliesSubReply: [],
            threadColumns: [
                { title: 'tid', dataIndex: 'tid', scopedSlots: { customRender: 'tid' } },
                { title: '标题', dataIndex: 'title', scopedSlots: { customRender: 'titleWithTag' } },
                { title: '回复量', dataIndex: 'replyNum' },
                { title: '浏览量', dataIndex: 'viewNum' },
                { title: '发帖人', scopedSlots: { customRender: 'authorInfo' } },
                { title: '发帖时间', dataIndex: 'postTime' },
                { title: '最后回复人', scopedSlots: { customRender: 'latestReplierInfo' } },
                { title: '最后回复时间', dataIndex: 'latestReplyTime' },
                { title: '发帖人UID', dataIndex: 'authorUid' },
                { title: '最后回复人UID', dataIndex: 'latestReplierUid' },
                { title: '1楼pid', dataIndex: 'firstPid', scopedSlots: { customRender: 'firstPid' } },
                { title: '主题贴类型', dataIndex: 'threadType' }, // todo: unknown value enum struct
                { title: '分享量', dataIndex: 'shareNum' },
                { title: '赞踩量', dataIndex: 'agreeInfo' }, // todo: unknown json struct
                { title: '旧版客户端赞', dataIndex: 'zanInfo' }, // todo: unknown json struct
                { title: '发帖位置', dataIndex: 'location' }, // todo: unknown json struct
                { title: '首次收录时间', dataIndex: 'created_at' },
                { title: '最后更新时间', dataIndex: 'updated_at' }
            ],
            replyColumns: [
                { title: 'pid', dataIndex: 'pid' },
                { title: '楼层', dataIndex: 'floor' },
                { title: '楼中楼回复量', dataIndex: 'subReplyNum' },
                { title: '发帖人', scopedSlots: { customRender: 'authorInfo' } },
                { title: '发帖人UID', dataIndex: 'authorUid' },
                { title: '发帖时间', dataIndex: 'postTime' },
                { title: '是否折叠', dataIndex: 'isFold' }, // todo: unknown value enum struct
                { title: '赞踩量', dataIndex: 'agreeInfo' }, // todo: unknown json struct
                { title: '客户端小尾巴', dataIndex: 'signInfo' }, // todo: unknown json struct
                { title: '发帖来源', dataIndex: 'tailInfo' }, // todo: unknown json struct
                { title: '发帖位置', dataIndex: 'location' }, // todo: unknown json struct
                { title: '首次收录时间', dataIndex: 'created_at' },
                { title: '最后更新时间', dataIndex: 'updated_at' }
            ],
            subReplyColumns: [
                { title: 'spid', dataIndex: 'spid' },
                { title: '发帖人', scopedSlots: { customRender: 'authorInfo' } },
                { title: '发帖人UID', dataIndex: 'authorUid' },
                { title: '发帖时间', dataIndex: 'postTime' },
                { title: '首次收录时间', dataIndex: 'created_at' },
                { title: '最后更新时间', dataIndex: 'updated_at' }
            ]
        });
        const renderUsername = uid => {
            const user = state.$getUserInfo(uid);
            const { name } = user;
            const { displayName } = user;
            if (name === null) return `${displayName !== null ? `${displayName}` : `无用户名或覆盖名（UID：${user.uid}）`}`;
            return `${name} ${displayName !== null ? `（${displayName}）` : ''}`;
        };

        onMounted(() => {
            state.threads = props.posts.threads;
            state.threadsReply = _.chain(state.threads)
                .map('replies')
                .reject(_.isEmpty) // remove threads which haven't reply
                .mapKeys(replies => replies[0].tid) // convert threads' reply array to object for adding tid key
                .value();
            state.repliesSubReply = _.chain(state.threadsReply)
                .toArray() // tid keyed object to array
                .flatten() // flatten every thread's replies
                .map('subReplies')
                .reject(_.isEmpty) // remove replies which haven't sub reply
                .mapKeys(subReplies => subReplies[0].pid) // convert replies' sub reply array to object for adding pid key
                .value();
        });

        return { _, ...toRefs(state), renderUsername };
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
/* <post-render-table> */
.render-table-thread .ant-table {
    width: fit-content;
}

.render-table-thread > .ant-spin-nested-loading > .ant-spin-container > .ant-table { /* dom struct might change in further antd updates */
    width: auto;
    border: 1px solid #e8e8e8;
    border-radius: 4px 4px 0 0;
}

.render-table-thread .ant-table td, .render-table-thread .ant-table td *, .render-table-thread .ant-table th {
    white-space: nowrap;
    font-family: Consolas, Courier New, monospace;
}

.render-table-thread .ant-table-expand-icon-th, .render-table-thread .ant-table-row-expand-icon-cell {
    width: 1px; /* any value other than 0px */
    min-width: unset;
    padding-left: 5px !important;
    padding-right: 0 !important;
}
</style>
