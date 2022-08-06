<template>
    <Table :columns="threadColumns" :dataSource="threads" :defaultExpandAllRows="true"
           :expandRowByClick="true" :pagination="false"
           rowKey="tid" size="middle" class="render-table-thread">
        <template #tid="{ record: { tid } }">
            <RouterLink :to="{ name: 'post/tid', params: { tid } }">{{ tid }}</RouterLink>
        </template>
        <template #firstPid="{ record: { firstPid } }">
            <RouterLink :to="{ name: 'post/pid', params: { pid: firstPid } }">{{ firstPid }}</RouterLink>
        </template>
        <template #titleWithTag="{ record }">
            <ThreadTag :thread="record" />
            <span>{{ record.title }}</span>
        </template>
        <template #authorInfo="{ record: { authorUid, authorManagerType } }">
            <a :href="tiebaUserLink(getUser(authorUid).name)" target="_blank">
                <img :data-src="tiebaUserPortraitUrl(getUser(authorUid).avatarUrl)"
                     class="tieba-user-portrait-small lazy" /> {{ renderUsername(authorUid) }}
            </a>
            <UserTag :user="{ managerType: authorManagerType }" />
        </template>
        <template #latestReplierInfo="{ record: { latestReplierUid } }">
            <a :href="tiebaUserLink(getUser(latestReplierUid).name)" target="_blank">
                <img :data-src="tiebaUserPortraitUrl(getUser(latestReplierUid).avatarUrl)"
                     class="tieba-user-portrait-small lazy" /> {{ renderUsername(latestReplierUid) }}
            </a>
        </template>
        <template #expandedRowRender="{ record: { tid, authorUid: threadAuthorUid } }">
            <span v-if="threadsReply[tid] === undefined">无子回复帖</span>
            <Table v-else :columns="replyColumns" :dataSource="threadsReply[tid]"
                   :defaultExpandAllRows="true" :expandRowByClick="true" :pagination="false" rowKey="pid" size="middle">
                <template #authorInfo="{ record: { authorUid, authorManagerType, authorExpGrade } }">
                    <a :href="tiebaUserLink(getUser(authorUid).name)" target="_blank">
                        <img :data-src="tiebaUserPortraitUrl(getUser(authorUid).avatarUrl)"
                             class="tieba-user-portrait-small lazy" /> {{ renderUsername(authorUid) }}
                    </a>
                    <UserTag :user="{
                        uid: { current: authorUid, thread: threadAuthorUid },
                        managerType: authorManagerType,
                        expGrade: authorExpGrade
                    }" />
                </template>
                <template #expandedRowRender="{ record: { pid, content, authorUid: replyAuthorUid } }">
                    <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component vue/no-v-html -->
                    <component :is="repliesSubReply[pid] === undefined ? 'span' : 'p'" v-viewer.static v-html="content" />
                    <Table v-if="repliesSubReply[pid] !== undefined"
                           :columns="subReplyColumns" :dataSource="repliesSubReply[pid]"
                           :defaultExpandAllRows="true" :expandRowByClick="true" :pagination="false" rowKey="spid" size="middle">
                        <template #authorInfo="{ record: { authorUid, authorManagerType, authorExpGrade } }">
                            <a :href="tiebaUserLink(getUser(authorUid).name)" target="_blank">
                                <img :data-src="tiebaUserPortraitUrl(getUser(authorUid).avatarUrl)"
                                     class="tieba-user-portrait-small lazy" /> {{ renderUsername(authorUid) }}
                            </a>
                            <UserTag :user="{
                                uid: { current: authorUid, thread: threadAuthorUid, reply: replyAuthorUid },
                                managerType: authorManagerType,
                                expGrade: authorExpGrade
                            }" />
                        </template>
                        <template #expandedRowRender="{ record: { content: subReplyContent } }">
                            <span v-viewer.static v-html="subReplyContent" />
                        </template>
                    </Table>
                </template>
            </Table>
        </template>
    </Table>
</template>

<script lang="ts">
import { ThreadTag, UserTag } from './';
import { baseGetUser, baseRenderUsername } from './viewListAndTableCommon';
import type { ApiPostsQuery, SubReplyRecord } from '@/api/index.d';
import type { Pid, Tid } from '@/shared';
import { tiebaUserLink, tiebaUserPortraitUrl } from '@/shared';

import type { PropType } from 'vue';
import { defineComponent, onMounted, reactive, toRefs } from 'vue';
import { RouterLink } from 'vue-router';
import type { ColumnType } from 'ant-design-vue/es/table/interface';
import { Table } from 'ant-design-vue';
import _ from 'lodash';

export default defineComponent({
    components: { RouterLink, Table, ThreadTag, UserTag },
    props: {
        posts: { type: Object as PropType<ApiPostsQuery>, required: true }
    },
    setup(props) {
        const state = reactive<{
            threads: ApiPostsQuery['threads'],
            threadsReply: Record<Tid, ApiPostsQuery['threads'][number]['replies']>,
            repliesSubReply: Record<Pid, SubReplyRecord[]>,
            threadColumns: ColumnType[],
            replyColumns: ColumnType[],
            subReplyColumns: ColumnType[]
        }>({
            threads: [],
            threadsReply: [],
            repliesSubReply: [],
            threadColumns: [
                { title: 'tid', dataIndex: 'tid', slots: { customRender: 'tid' } },
                { title: '标题', dataIndex: 'title', slots: { customRender: 'titleWithTag' } },
                { title: '回复量', dataIndex: 'replyNum' },
                { title: '浏览量', dataIndex: 'viewNum' },
                { title: '发帖人', slots: { customRender: 'authorInfo' } },
                { title: '发帖时间', dataIndex: 'postTime' },
                { title: '最后回复人', slots: { customRender: 'latestReplierInfo' } },
                { title: '最后回复时间', dataIndex: 'latestReplyTime' },
                { title: '发帖人UID', dataIndex: 'authorUid' },
                { title: '最后回复人UID', dataIndex: 'latestReplierUid' },
                { title: '1楼pid', dataIndex: 'firstPid', slots: { customRender: 'firstPid' } },
                { title: '主题帖类型', dataIndex: 'threadType' }, // todo: unknown value enum struct
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
                { title: '发帖人', slots: { customRender: 'authorInfo' } },
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
                { title: '发帖人', slots: { customRender: 'authorInfo' } },
                { title: '发帖人UID', dataIndex: 'authorUid' },
                { title: '发帖时间', dataIndex: 'postTime' },
                { title: '首次收录时间', dataIndex: 'created_at' },
                { title: '最后更新时间', dataIndex: 'updated_at' }
            ]
        });
        const getUser = baseGetUser(props.posts.users);
        const renderUsername = baseRenderUsername(getUser);

        onMounted(() => {
            state.threads = props.posts.threads;
            state.threadsReply = _.chain(state.threads)
                .map(i => i.replies)
                .reject(_.isEmpty) // remove threads which have no reply
                .mapKeys(replies => replies[0].tid) // convert threads' reply array to object for adding tid key
                .value();
            state.repliesSubReply = _.chain(state.threadsReply)
                .toArray() // cast tid keyed object to array
                .flatten() // flatten every thread's replies
                .map(i => i.subReplies)
                .reject(_.isEmpty) // remove replies which have no sub reply
                .mapKeys(subReplies => subReplies[0].pid) // cast replies' sub reply from array to object which keyed by pid
                .value();
        });

        return { tiebaUserLink, tiebaUserPortraitUrl, ...toRefs(state), getUser, renderUsername };
    }
});
</script>

<style>
.render-table-thread .ant-table {
    width: fit-content; /* narrow the width of reply and sub reply table to prevent they stretch with thread table */
}

.render-table-thread > .ant-spin-nested-loading > .ant-spin-container > .ant-table {
    /* select the outermost thread table, might change in further antd updates */
    width: auto;
    border: 1px solid #e8e8e8;
    border-radius: 4px 4px 0 0;
}

.render-table-thread .ant-table td, .render-table-thread .ant-table td > *, .render-table-thread .ant-table th {
    white-space: nowrap;
    font-family: Consolas, Courier New, monospace;
}

.render-table-thread .ant-table-expand-icon-th, .render-table-thread .ant-table-row-expand-icon-cell {
    /* shrink the width of expanding child posts table button */
    width: auto;
    min-width: auto;
    padding-left: 5px !important;
    padding-right: 0 !important;
}
</style>
