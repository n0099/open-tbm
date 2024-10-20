<template>
<DefineUser v-slot="{ user, threadAuthorUid, replyAuthorUid }">
    <NuxtLink :to="toUserProfileUrl(user)" noPrefetch>
        <UserPortrait :user="user" size="small" />
        {{ renderUsername(user.uid) }}
    </NuxtLink>
    <PostBadgeUser
        :user="user" class="ms-1"
        :threadAuthorUid="threadAuthorUid"
        :replyAuthorUid="replyAuthorUid" />
</DefineUser>
<DefineLatestReplier v-slot="{ latestReplier }">
    <template v-if="latestReplier !== undefined">
        <PostBadgeThreadLatestReplier
            v-if="latestReplier.uid === null
                && !(latestReplier.name === null && latestReplier.displayName === null)"
            :users="expandLatestReplierToRoutes(latestReplier)" />
        <ReuseUser v-else-if="latestReplier.uid !== null" :user="getUser(latestReplier.uid)" />
    </template>
</DefineLatestReplier>
<ATable
    :dataSource="posts.threads" :columns="threadColumns" rowKey="tid"
    defaultExpandAllRows :pagination="false" size="middle" bordered>
    <template #bodyCell="{ column: { dataIndex: column }, record }">
        <template v-if="column === 'tid'">
            <template v-for="tid in [(record as Thread).tid]" :key="tid">
                <NuxtLink :to="{ name: 'posts/tid', params: { tid } }">{{ tid }}</NuxtLink>
            </template>
        </template>
        <template v-else-if="column === 'titleWithTag'">
            <template v-for="thread in [record as Thread]" :key="thread.tid">
                <PostBadgeThread :thread="thread" />
                <span>{{ thread.title }}</span>
            </template>
        </template>
        <template v-else-if="column === 'author'">
            <ReuseUser :user="getUser((record as Thread).authorUid)" />
        </template>
        <template v-else-if="column === 'latestReplier' && (record as Thread).latestReplierId !== null">
            <ReuseLatestReplier :latestReplier="getLatestReplier((record as Thread).latestReplierId)" />
        </template>
        <template v-else-if="column === 'latestReplierUid' && (record as Thread).latestReplierId !== null">
            {{ getLatestReplier((record as Thread).latestReplierId)?.uid }}
        </template>
    </template>
    <template #expandedRowRender="{ record: { authorUid: threadAuthorUid, replies } }">
        <ATable
            v-if="!_.isEmpty(replies)"
            :dataSource="replies" :columns="replyColumns" rowKey="pid"
            defaultExpandAllRows :pagination="false"
            size="middle" class="renderer-table-reply" bordered>
            <template #bodyCell="{ column: { dataIndex: column }, record }">
                <template v-if="column === 'author'">
                    <ReuseUser
                        :user="getUser((record as Reply).authorUid)"
                        :threadAuthorUid="threadAuthorUid" />
                </template>
            </template>
            <template #expandedRowRender="{ record: { content, authorUid: replyAuthorUid, subReplies } }">
                <PostRendererContent :content="content" />
                <ATable
                    v-if="!_.isEmpty(subReplies)"
                    :dataSource="subReplies" :columns="subReplyColumns" rowKey="spid"
                    defaultExpandAllRows :pagination="false"
                    size="middle" class="renderer-table-sub-reply" bordered>
                    <template #bodyCell="{ column: { dataIndex: column }, record }">
                        <template v-if="column === 'author'">
                            <ReuseUser
                                :user="getUser((record as SubReply).authorUid)"
                                :threadAuthorUid="threadAuthorUid"
                                :replyAuthorUid="replyAuthorUid" />
                        </template>
                    </template>
                    <template #expandedRowRender="{ record: { content: subReplyContent } }">
                        <PostRendererContent :content="subReplyContent" />
                    </template>
                </ATable>
            </template>
        </ATable>
    </template>
</ATable>
</template>

<script setup lang="ts">
import { expandLatestReplierToRoutes } from '@/components/post/badge/ThreadLatestReplier.vue';
import type User from '@/components/post/badge/User.vue';
import type { ColumnType } from 'ant-design-vue/es/table/interface';
import _ from 'lodash';

defineProps<{ posts: ApiPosts['response'] }>();
const [DefineUser, ReuseUser] = createReusableTemplate<InstanceType<typeof User>['$props']>();
const [DefineLatestReplier, ReuseLatestReplier] = createReusableTemplate<{ latestReplier?: LatestReplier }>();
const { getUser, renderUsername, getLatestReplier } = usePostPageProvision().inject();
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
</script>

<style scoped>
:deep(.renderer-table-sub-reply .ant-table) {
    margin-block-start: 0 !important;
}

:deep(.ant-table) {
    inline-size: max-content;
}

:deep(.ant-table td, .ant-table td > *, .ant-table th) {
    font-family: monospace;
}

:deep(.ant-table-expand-icon-col) {
    width: 0 !important;
}

:deep(.ant-table-cell) {
    padding: .5rem;
}

:deep(.ant-table thead > tr > th) {
    padding-top: .5rem !important;
    padding-bottom: .5rem !important;
}
:deep(.ant-table thead > tr) {
    position: sticky;
    top: 0;
    line-height: 1.5rem;
    z-index: 1022;
}
:deep(.renderer-table-reply thead > tr) {
    top: 2.5rem;
    z-index: 1021;
}
:deep(.renderer-table-sub-reply thead > tr) {
    top: 5rem;
    z-index: 1020;
}

:deep(.renderer-table-reply .ant-table table) {
    min-inline-size: calc(100vw - v-bind(scrollBarWidth) - 48px);
}
:deep(.renderer-table-reply .ant-table tbody > tr > td) {
    max-inline-size: calc(100vw - v-bind(scrollBarWidth) - 48px);
}
:deep(.renderer-table-sub-reply .ant-table table) {
    min-inline-size: calc(100vw - v-bind(scrollBarWidth) - 48px - 48px);
}
:deep(.renderer-table-sub-reply .ant-table tbody > tr > td) {
    max-inline-size: calc(100vw - v-bind(scrollBarWidth) - 48px - 48px);
}

:deep(.renderer-table-reply thead > tr > th) {
    border-color: var(--bs-info);
}
:deep(.renderer-table-sub-reply thead > tr > th) {
    border-color: var(--bs-success);
}
</style>
