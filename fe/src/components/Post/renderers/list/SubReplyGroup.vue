<template>
    <div class="sub-reply-group bs-callout bs-callout-success">
        <ul class="list-group list-group-flush">
            <li v-for="(subReply, subReplyGroupIndex) in subReplyGroup" :key="subReply.spid"
                @mouseenter="() => { hoveringSubReplyID = subReply.spid }"
                @mouseleave="() => { hoveringSubReplyID = 0 }"
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
                                   :threadAuthorUid="threadAuthorUid"
                                   :replyAuthorUid="replyAuthorUid" />
                    </RouterLink>
                    <div class="float-end badge bg-light fs-6 p-1 pe-2" role="group">
                        <div class="d-inline" :class="{ invisible: hoveringSubReplyID !== subReply.spid }">
                            <PostCommonMetadataIconLinks :post="subReply" postTypeID="spid" />
                        </div>
                        <BadgePostTime :previousPostTime="(subReplyGroup[subReplyGroupIndex - 1] ?? previousSubReplyGroup?.at(-1))?.postedAt"
                                       :currentPostTime="subReply.postedAt"
                                       :nextPostTime="(subReplyGroup[subReplyGroupIndex - 1] ?? nextSubReplyGroup?.[0])?.postedAt"
                                       timestampType="发帖时间" class="bg-info" />
                    </div>
                </template>
                <div v-viewer.static class="sub-reply-content" v-html="subReply.content" />
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import type { UserProvision } from './RendererList.vue';
import BadgePostTime from '@/components/Post/badges/BadgePostTime.vue';
import BadgeUser from '@/components/Post/badges/BadgeUser.vue';
import PostCommonMetadataIconLinks from '@/components/Post/badges/PostCommonMetadataIconLinks.vue';
import type { SubReply } from '@/api/post';
import type { BaiduUserID } from '@/api/user';
import { toUserPortraitImageUrl, toUserRoute } from '@/shared';
import { inject, ref } from 'vue';
import { RouterLink } from 'vue-router';

defineProps<{
    previousSubReplyGroup?: SubReply[],
    subReplyGroup: SubReply[],
    nextSubReplyGroup?: SubReply[],
    threadAuthorUid: BaiduUserID,
    replyAuthorUid: BaiduUserID
}>();
// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
const { getUser, renderUsername } = inject<UserProvision>('userProvision')!;
const hoveringSubReplyID = ref(0);
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
