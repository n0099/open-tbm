<template>
<div :data-cursor="nestedPosts.pages.currentCursor" class="post-render-list pb-3">
    <PostRendererListThread
        v-for="(thread, index) in nestedPosts.threads" :key="thread.tid"
        :previousThread="nestedPosts.threads[index - 1]" :thread="thread"
        :nextThread="nestedPosts.threads[index + 1]" :replyElementRefs="replyElementRefs" />
</div>
</template>

<script setup lang="ts">
import type { RouterScrollBehavior } from 'vue-router';
import _ from 'lodash';

const { posts } = defineProps<{ posts: ApiPosts['response'] }>();
const nestedPosts = computed(() => {
    const newPosts = refDeepClone(posts) as // https://github.com/microsoft/TypeScript/issues/33591
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

if (import.meta.client) {
    useRouteScrollBehaviorStore().set((to, from): ReturnType<RouterScrollBehavior> => {
        if (!compareRouteIsNewQuery(to, from))
            return postListItemScrollPosition(to);

        return undefined;
    });
}

const replyElementRefs = useTemplateRefsList<HTMLElement>();
onMounted(async () => {
    await nextTick();
    guessReplyContainIntrinsicBlockSize(replyElementRefs.value);
});
</script>
