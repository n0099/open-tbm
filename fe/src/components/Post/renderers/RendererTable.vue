<template>
    <Table :columns="threadColumns" :dataSource="threads" defaultExpandAllRows
           expandRowByClick :pagination="false"
           rowKey="tid" size="middle" class="render-table-thread">
        <template #bodyCell="{ column: { dataIndex: column }, record }">
            <template v-if="column === 'tid'">
                <template v-for="tid in [(record as Thread).tid]" :key="tid">
                    <RouterLink :to="{ name: 'post/tid', params: { tid } }">{{ tid }}</RouterLink>
                </template>
            </template>
            <template v-else-if="column === 'titleWithTag'">
                <template v-for="thread in [record as Thread]" :key="thread.tid">
                    <BadgeThread :thread="thread" />
                    <span>{{ thread.title }}</span>
                </template>
            </template>
            <template v-else-if="column === 'author'">
                <template v-for="user in [getUser((record as Thread).authorUid)]" :key="user.uid">
                    <a :href="toUserProfileUrl(user)">
                        <img :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                             class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                    </a>
                    <BadgeUser :user="user" />
                </template>
            </template>
            <template v-else-if="column === 'latestReplier' && (record as Thread).latestReplierUid !== null">
                <template v-for="user in [getUser(record.latestReplierUid)]" :key="user.uid">
                    <a :href="toUserProfileUrl(user)">
                        <img :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                             class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                    </a>
                </template>
            </template>
        </template>
        <template #expandedRowRender="{ record: { tid, authorUid: threadAuthorUid } }">
            <span v-if="repliesKeyByTid[tid] === undefined">无子回复帖</span>
            <Table v-else :columns="replyColumns" :dataSource="repliesKeyByTid[tid]"
                   defaultExpandAllRows expandRowByClick
                   :pagination="false" rowKey="pid" size="middle">
                <template #bodyCell="{ column: { dataIndex: column }, record }">
                    <template v-if="column === 'author'">
                        <template v-for="user in [getUser((record as Reply).authorUid)]" :key="user.uid">
                            <a :href="toUserProfileUrl(user)">
                                <img :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                                     class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                            </a>
                            <BadgeUser :user="user" :threadAuthorUid="threadAuthorUid" />
                        </template>
                    </template>
                </template>
                <template #expandedRowRender="{ record: { pid, content, authorUid: replyAuthorUid } }">
                    <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component vue/no-v-html -->
                    <component :is="subRepliesKeyByPid[pid] === undefined
                                   ? 'span'
                                   : 'p'"
                               v-viewer.static v-html="content" />
                    <Table v-if="subRepliesKeyByPid[pid] !== undefined"
                           :columns="subReplyColumns" :dataSource="subRepliesKeyByPid[pid]"
                           defaultExpandAllRows expandRowByClick
                           :pagination="false" rowKey="spid" size="middle">
                        <template #bodyCell="{ column: { dataIndex: column }, record }">
                            <template v-if="column === 'author'">
                                <template v-for="user in [getUser((record as SubReply).authorUid)]" :key="user.uid">
                                    <a :href="toUserProfileUrl(user)">
                                        <img :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                                             class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                                    </a>
                                    <BadgeUser :user="user"
                                               :threadAuthorUid="threadAuthorUid"
                                               :replyAuthorUid="replyAuthorUid" />
                                </template>
                            </template>
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

<script setup lang="ts">
import { baseGetUser, baseRenderUsername } from './common';
import BadgeThread from '../badges/BadgeThread.vue';
import BadgeUser from '../badges/BadgeUser.vue';

import type { ApiPosts } from '@/api/index.d';
import type { Reply, SubReply, Thread } from '@/api/post';
import type { Pid, Tid } from '@/shared';
import { toUserPortraitImageUrl, toUserProfileUrl } from '@/shared';

import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Table } from 'ant-design-vue';
import type { ColumnType } from 'ant-design-vue/es/table/interface';
import * as _ from 'lodash-es';

const props = defineProps<{ posts: ApiPosts }>();
const threads = ref<ApiPosts['threads']>();
const repliesKeyByTid = ref<Record<Tid, ApiPosts['threads'][number]['replies']>>([]);
const subRepliesKeyByPid = ref<Record<Pid, SubReply[]>>([]);
const threadColumns = ref<ColumnType[]>([
    { title: 'tid', dataIndex: 'tid' },
    { title: '标题', dataIndex: 'title' },
    { title: '回复量', dataIndex: 'replyCount' },
    { title: '浏览量', dataIndex: 'viewCount' },
    { title: '发帖人', dataIndex: 'author' },
    { title: '发帖时间', dataIndex: 'postedAt' },
    { title: '最后回复人', dataIndex: 'latestReplier' },
    { title: '最后回复时间', dataIndex: 'latestReplyPostedAt' },
    { title: '发帖人百度UID', dataIndex: 'authorUid' },
    { title: '最后回复人百度UID', dataIndex: 'latestReplierUid' },
    { title: '主题帖类型', dataIndex: 'threadType' }, // todo: unknown value enum struct
    { title: '分享量', dataIndex: 'shareCount' },
    { title: '赞踩量', dataIndex: 'agree' }, // todo: unknown json struct
    { title: '旧版客户端赞', dataIndex: 'zan' }, // todo: unknown json struct
    { title: '发帖位置', dataIndex: 'geolocation' }, // todo: unknown json struct
    { title: '首次收录时间', dataIndex: 'createdAt' },
    { title: '最后更新时间', dataIndex: 'updatedAt' }
]);
const replyColumns = ref<ColumnType[]>([
    { title: 'pid', dataIndex: 'pid' },
    { title: '楼层', dataIndex: 'floor' },
    { title: '楼中楼回复量', dataIndex: 'subReplyCount' },
    { title: '发帖人', dataIndex: 'author' },
    { title: '发帖人百度UID', dataIndex: 'authorUid' },
    { title: '发帖时间', dataIndex: 'postedAt' },
    { title: '是否折叠', dataIndex: 'isFold' }, // todo: unknown value enum struct
    { title: '赞踩量', dataIndex: 'agree' }, // todo: unknown json struct
    { title: '客户端小尾巴', dataIndex: 'sign' }, // todo: unknown json struct
    { title: '发帖来源', dataIndex: 'tail' }, // todo: unknown json struct
    { title: '发帖位置', dataIndex: 'geolocation' }, // todo: unknown json struct
    { title: '首次收录时间', dataIndex: 'createdAt' },
    { title: '最后更新时间', dataIndex: 'updatedAt' }
]);
const subReplyColumns = ref<ColumnType[]>([
    { title: 'spid', dataIndex: 'spid' },
    { title: '发帖人', dataIndex: 'author' },
    { title: '发帖人百度UID', dataIndex: 'authorUid' },
    { title: '发帖时间', dataIndex: 'postedAt' },
    { title: '首次收录时间', dataIndex: 'createdAt' },
    { title: '最后更新时间', dataIndex: 'updatedAt' }
]);

const getUser = baseGetUser(props.posts.users);
const renderUsername = baseRenderUsername(getUser);

onMounted(() => {
    threads.value = props.posts.threads;
    repliesKeyByTid.value = _.chain(threads.value)
        .map(i => i.replies)
        .reject(_.isEmpty) // remove threads which have no reply
        .mapKeys(replies => replies[0].tid) // convert threads' reply array to object for adding tid key
        .value();
    subRepliesKeyByPid.value = _.chain(repliesKeyByTid.value)
        .toArray() // cast tid keyed object to array
        .flatten() // flatten every thread's replies
        .map(i => i.subReplies)
        .reject(_.isEmpty) // remove replies which have no sub reply
        .mapKeys(subReplies => subReplies[0].pid) // cast replies' sub reply from array to object which key by pid
        .value();
});
</script>

<style scoped>
:deep(.render-table-thread .ant-table) {
    /* narrow the inline-size of reply and sub reply table to prevent they stretch with thread table */
    inline-size: fit-content;
}

:deep(.render-table-thread > .ant-spin-nested-loading > .ant-spin-container > .ant-table) {
    /* select the outermost thread table, might change in further antd updates */
    inline-size: auto;
    border: 1px solid #e8e8e8;
    border-radius: 4px 4px 0 0;
}

:deep(.render-table-thread .ant-table td, .render-table-thread .ant-table td > *, .render-table-thread .ant-table th) {
    white-space: nowrap;
    font-family: Consolas, Courier New, monospace;
}

:deep(.render-table-thread .ant-table-expand-icon-th, .render-table-thread .ant-table-row-expand-icon-cell) {
    /* shrink the inline-size of expanding child posts table button */
    inline-size: auto;
    min-inline-size: auto;
    padding-inline-start: 5px !important;
    padding-inline-end: 0 !important;
}
</style>
