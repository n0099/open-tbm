<template>
    <div class="sub-reply-group bs-callout bs-callout-success">
        <ul class="list-group list-group-flush">
            <li v-for="(subReply, subReplyGroupIndex) in subReplyGroup" :key="subReply.spid"
                :class="{ 'highlight-post': highlightPostStore.isHighlightingPost(subReply, 'spid') }"
                class="sub-reply-item list-group-item">
                <template v-for="author in [getUser(subReply.authorUid)]" :key="author.uid">
                    <RouterLink v-if="subReplyGroup[subReplyGroupIndex - 1] === undefined" :to="toUserRoute(author.uid)"
                                class="sub-reply-author text-wrap badge bg-light">
                        <img :src="toUserPortraitImageUrl(author.portrait)"
                             loading="lazy" class="tieba-user-portrait-small" />
                        <span class="mx-2 align-middle link-dark">
                            {{ renderUsername(subReply.authorUid) }}
                        </span>
                        <BadgeUser :user="getUser(subReply.authorUid)"
                                   :threadAuthorUid="thread.authorUid"
                                   :replyAuthorUid="reply.authorUid" />
                    </RouterLink>
                    <div class="float-end badge bg-light fs-6 p-1 pe-2" role="group">
                        <BadgePostCommon :post="subReply" postIDKey="spid" postTypeText="楼中楼" />
                        <BadgePostTime postType="楼中楼"
                                       :parentPost="reply" parentPostIDKey="pid"
                                       :currentPost="subReply" currentPostIDKey="spid"
                                       :previousPost="getSiblingSubReply(subReplyGroupIndex, 'previous')"
                                       :nextPost="getSiblingSubReply(subReplyGroupIndex, 'next')"
                                       postTimeKey="postedAt" timestampType="发帖时间"
                                       class="bg-info" />
                    </div>
                </template>
                <div v-viewer.static class="sub-reply-content" v-html="subReply.content" />
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import type { UserProvision } from './RendererList.vue';
import BadgePostCommon from '@/components/Post/badges/BadgePostCommon.vue';
import BadgePostTime from '@/components/Post/badges/BadgePostTime.vue';
import BadgeUser from '@/components/Post/badges/BadgeUser.vue';
import type { Reply, SubReply, Thread } from '@/api/post';
import { toUserPortraitImageUrl, toUserRoute } from '@/shared';
import { useHighlightPostStore } from '@/stores/highlightPost';
import { inject } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps<{
    thread: Thread,
    reply: Reply,
    previousSubReplyGroup?: SubReply[],
    subReplyGroup: SubReply[],
    nextSubReplyGroup?: SubReply[]
}>();
const highlightPostStore = useHighlightPostStore();

// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
const { getUser, renderUsername } = inject<UserProvision>('userProvision')!;
const getSiblingSubReply = (index: number, direction: 'previous' | 'next') =>
    props.subReplyGroup[index + (direction === 'next' ? 1 : -1)] as SubReply | undefined
        ?? (direction === 'next' ? props.nextSubReplyGroup?.[0] : props.previousSubReplyGroup?.at(-1));
</script>

<style scoped>
.sub-reply-group {
    margin-block-start: .5rem !important;
    margin-inline-start: .5rem;
    padding: .25rem;
}
.sub-reply-item {
    padding: .125rem;
}
.sub-reply-item > * {
    padding: .25rem;
}
.sub-reply-author {
    font-size: .9rem;
}
</style>
