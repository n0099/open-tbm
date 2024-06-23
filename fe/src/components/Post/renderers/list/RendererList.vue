<template>
    <div :data-cursor="posts.pages.currentCursor" class="post-render-list pb-3">
        <ThreadItem v-for="(thread, index) in posts.threads" :key="thread.tid"
                    :previousThread="posts.threads[index - 1]" :thread="thread"
                    :nextThread="posts.threads[index + 1]" />
    </div>
</template>

<script setup lang="ts">
import type { RouterScrollBehavior } from 'vue-router';
import _ from 'lodash';

const props = defineProps<{ initialPosts: ApiPosts['response'] }>();
const getUser = computed(() => baseGetUser(props.initialPosts.users));
const renderUsername = computed(() => baseRenderUsername(getUser.value));
const userProvision = { getUser, renderUsername };

// export type UserProvision = typeof userProvision;
// will trigger @typescript-eslint/no-unsafe-assignment when `inject<UserProvision>('userProvision')`
export interface UserProvision {
    getUser: ComputedRef<ReturnType<typeof baseGetUser>>,
    renderUsername: ComputedRef<ReturnType<typeof baseRenderUsername>>
}
provide<UserProvision>('userProvision', userProvision);

export type ThreadWithGroupedSubReplies<AdditionalSubReply extends SubReply = never> =
    Thread & { replies: Array<Reply & { subReplies: Array<AdditionalSubReply | SubReply[]> }> };
const posts = computed(() => {
    // https://github.com/microsoft/TypeScript/issues/33591
    const newPosts = refDeepClone(props.initialPosts) as
        Modify<ApiPosts['response'], { threads: Array<ThreadWithGroupedSubReplies<SubReply>> }>;
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

    return newPosts as Modify<ApiPosts['response'], { threads: ThreadWithGroupedSubReplies[] }>;
});

onMounted(initialTippy);
if (import.meta.client) {
    useRouteScrollBehaviorStore().set((to, from): ReturnType<RouterScrollBehavior> => {
        if (!compareRouteIsNewQuery(to, from))
            return postListItemScrollPosition(to);

        return undefined;
    });
}
</script>
