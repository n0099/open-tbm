<template>
<ATable
    :dataSource="props.posts.threads" :columns="threadColumns" rowKey="tid"
    defaultExpandAllRows expandRowByClick :pagination="false" size="middle">
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
            <template v-for="user in [getUser((record as Thread).authorUid)]" :key="user.uid">
                <NuxtLink :to="toUserProfileUrl(user)" noPrefetch>
                    <img
                        :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                        class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                </NuxtLink>
                <PostBadgeUser :user="user" class="ms-1" />
            </template>
        </template>
        <template v-else-if="column === 'latestReplier' && (record as Thread).latestReplierUid !== null">
            <template v-for="user in [getUser(record.latestReplierUid)]" :key="user.uid">
                <NuxtLink :to="toUserProfileUrl(user)" noPrefetch>
                    <img
                        :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                        class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                </NuxtLink>
                <PostBadgeUser :user="user" class="ms-1" />
            </template>
        </template>
    </template>
    <template #expandedRowRender="{ record: { authorUid: threadAuthorUid, replies } }">
        <ATable
            v-if="!_.isEmpty(replies)"
            :dataSource="replies" :columns="replyColumns" rowKey="pid"
            defaultExpandAllRows expandRowByClick :pagination="false"
            size="middle" class="renderer-table-reply">
            <template #bodyCell="{ column: { dataIndex: column }, record }">
                <template v-if="column === 'author'">
                    <template v-for="user in [getUser((record as Reply).authorUid)]" :key="user.uid">
                        <NuxtLink :to="toUserProfileUrl(user)" noPrefetch>
                            <img
                                :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                                class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                        </NuxtLink>
                        <PostBadgeUser :user="user" :threadAuthorUid="threadAuthorUid" class="ms-1" />
                    </template>
                </template>
            </template>
            <template #expandedRowRender="{ record: { content, authorUid: replyAuthorUid, subReplies } }">
                <PostRendererContent :content="content" />
                <ATable
                    v-if="!_.isEmpty(subReplies)"
                    :dataSource="subReplies" :columns="subReplyColumns" rowKey="spid"
                    defaultExpandAllRows expandRowByClick :pagination="false"
                    size="middle" class="renderer-table-sub-reply">
                    <template #bodyCell="{ column: { dataIndex: column }, record }">
                        <template v-if="column === 'author'">
                            <template v-for="user in [getUser((record as SubReply).authorUid)]" :key="user.uid">
                                <NuxtLink :to="toUserProfileUrl(user)" noPrefetch>
                                    <img
                                        :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                                        class="tieba-user-portrait-small" /> {{ renderUsername(user.uid) }}
                                </NuxtLink>
                                <PostBadgeUser
                                    :user="user" class="ms-1"
                                    :threadAuthorUid="threadAuthorUid"
                                    :replyAuthorUid="replyAuthorUid" />
                            </template>
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
import type { ColumnType } from 'ant-design-vue/es/table/interface';
import _ from 'lodash';

const props = defineProps<{ posts: ApiPosts['response'] }>();
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
const getUser = computed(() => baseGetUser(props.posts.users));
const renderUsername = computed(() => baseRenderUsername(getUser.value));
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

:deep(.ant-table thead > tr) {
    position: sticky;
    top: 0;
    z-index: 1020;
}
:deep(.renderer-table-reply thead > tr) {
    z-index: 1021;
}
:deep(.renderer-table-sub-reply thead > tr) {
    z-index: 1022;
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
