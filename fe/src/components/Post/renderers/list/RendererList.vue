<template>
    <div :data-cursor="posts.pages.currentCursor" class="post-render-list pb-3">
        <ThreadItem v-for="thread in posts.threads" :key="thread.tid" :thread="thread" />
    </div>
</template>

<script setup lang="ts">
import { postListItemScrollPosition } from './index';
import ThreadItem from './ThreadItem.vue';
import { baseGetUser, baseRenderUsername } from '../common';
import type { ApiPosts } from '@/api/index.d';
import type { Reply, SubReply, Thread } from '@/api/post';
import type { BaiduUserID } from '@/api/user';
import { compareRouteIsNewQuery, setComponentCustomScrollBehaviour } from '@/router';
import type { Modify } from '@/shared';
import { convertRemToPixels, isElementNode } from '@/shared';
import { initialTippy } from '@/shared/tippy';
import { computed, nextTick, onMounted } from 'vue';
import type { RouterScrollBehavior } from 'vue-router';
import * as _ from 'lodash-es';

const props = defineProps<{ initialPosts: ApiPosts['response'] }>();
const getUser = baseGetUser(props.initialPosts.users);
const renderUsername = baseRenderUsername(getUser);
const userRoute = (uid: BaiduUserID) => ({ name: 'user/uid', params: { uid } });
const posts = computed(() => {
    const newPosts = _.cloneDeep(props.initialPosts) as Modify<ApiPosts['response'], { // https://github.com/microsoft/TypeScript/issues/33591
        threads: Array<Thread & { replies: Array<Reply & { subReplies: Array<SubReply | SubReply[]> }> }>
    }>;
    newPosts.threads = newPosts.threads.map(thread => {
        thread.replies = thread.replies.map(reply => {
            // eslint-disable-next-line unicorn/no-array-reduce
            reply.subReplies = reply.subReplies.reduce<SubReply[][]>(
                (groupedSubReplies, subReply, index, subReplies) => {
                    if (_.isArray(subReply))
                        return [subReply]; // useless guard since subReply will never be an array at first
                    // group sub replies item by continuous and same post author
                    // https://github.com/microsoft/TypeScript/issues/13778
                    const previousSubReply = subReplies[index - 1] as SubReply | undefined;

                    if (previousSubReply !== undefined
                        && subReply.authorUid === previousSubReply.authorUid)
                        groupedSubReplies.at(-1)?.push(subReply); // append to last group
                    else
                        groupedSubReplies.push([subReply]); // new group

                    return groupedSubReplies;
                },
                []
            );

            return reply;
        });

        return thread;
    });

    return newPosts as Modify<ApiPosts['response'], {
        threads: Array<Thread & { replies: Array<Reply & { subReplies: SubReply[][] }> }>
    }>;
});

onMounted(initialTippy);
setComponentCustomScrollBehaviour((to, from): ReturnType<RouterScrollBehavior> => {
    if (!compareRouteIsNewQuery(to, from))
        return postListItemScrollPosition(to);

    return undefined;
});
</script>
