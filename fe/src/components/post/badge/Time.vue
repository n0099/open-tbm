<template>
<ClientOnly>
    <PostBadgeTimeView
        v-if="previousTime !== undefined && previousTime < currentTime"
        @mouseenter="() => previousPost !== undefined
            && highlightPostStore.set(previousPost, currentPostIDKey)"
        @mouseleave="() => highlightPostStore.unset()"
        :current="currentDateTime" :relativeTo="previousDateTime"
        :relativeToText="`相对于上一${postType}${timestampType}`"
        :postType="props.postType" :timestampType="timestampType" v-bind="$attrs">
        <!-- https://github.com/vuejs/language-tools/issues/4798 -->
        <FontAwesome :icon="faChevronUp" class="me-1 align-bottom" />
    </PostBadgeTimeView>
    <PostBadgeTimeView
        v-else-if="nextTime !== undefined && nextTime < currentTime"
        @mouseenter="() => nextPost !== undefined
            && highlightPostStore.set(nextPost, currentPostIDKey)"
        @mouseleave="() => highlightPostStore.unset()"
        :current="currentDateTime" :relativeTo="nextDateTime"
        :relativeToText="`相对于下一${postType}${timestampType}`"
        :postType="props.postType" :timestampType="timestampType" v-bind="$attrs">
        <FontAwesome :icon="faChevronDown" class="me-1 align-bottom" />
    </PostBadgeTimeView>
    <PostBadgeTimeView
        v-else-if="parentTime !== undefined && parentTime !== currentTime"
        @mouseenter="() => parentPost !== undefined
            && parentPostIDKey !== undefined
            && highlightPostStore.set(parentPost, parentPostIDKey)"
        @mouseleave="() => highlightPostStore.unset()"
        :current="currentDateTime" :relativeTo="parentDateTime"
        :relativeToText="`相对于所属${postTypeText[postTypeText.indexOf(postType) - 1]}${timestampType}`"
        :postType="props.postType" :timestampType="timestampType" v-bind="$attrs">
        <FontAwesome :icon="faAnglesUp" class="me-1 align-bottom" />
    </PostBadgeTimeView>
</ClientOnly>
<PostBadgeTimeView
    :current="currentDateTime" :postType="props.postType"
    :timestampType="timestampType" class="text-end"
    :class="{ 'post-badge-time-current-full': hydrationStore.isHydratingOrSSR }" v-bind="$attrs" />
</template>

<script setup lang="ts" generic="
    TPost extends Post,
    TParentPost extends TPost extends SubReply ? Reply
        : TPost extends Reply ? Thread
        : TPost extends Thread ? never : never,
    TPostIDKey extends keyof TPost & PostIDOf<TPost>,
    TParentPostIDKey extends keyof TParentPost & PostIDOf<TParentPost>,
    TPostTimeKey extends keyof TPost
        & keyof TParentPost
        & ('postedAt' | (TPost extends Thread ? 'latestReplyPostedAt' : never)),
    TPostTimeValue extends TPost['postedAt'] & (TPost extends Thread ? TPost['latestReplyPostedAt'] : unknown)">
import { faAnglesUp, faChevronDown, faChevronUp } from '@fortawesome/free-solid-svg-icons';
import { DateTime } from 'luxon';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    previousPost?: TPost,
    nextPost?: TPost,
    currentPost: TPost,
    currentPostIDKey: TPostIDKey,
    parentPost?: TParentPost,
    parentPostIDKey?: TParentPostIDKey,
    postType: PostTypeTextOf<TPost>,
    postTimeKey: TPostTimeKey,
    timestampType: 'latestReplyPostedAt' extends TPostTimeKey ? '最后回复时间'
        : 'postedAt' extends TPostTimeKey ? '发帖时间' : never
}>();
const highlightPostStore = useHighlightPostStore();
const hydrationStore = useHydrationStore();

const noScriptStyle = `<style>
    .post-badge-time-current-full {
        width: auto !important;
    }
</style>`; // https://github.com/nuxt/nuxt/issues/13848
useHead({ noscript: [{ innerHTML: noScriptStyle }] });

// https://github.com/typescript-eslint/typescript-eslint/issues/9723
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents, @typescript-eslint/no-unnecessary-type-parameters
const getPostTime = <T extends TPost | TParentPost>(post?: T) =>
    post?.[props.postTimeKey as keyof T] as TPostTimeValue | undefined;
const previousTime = computed(() => getPostTime(props.previousPost));
const nextTime = computed(() => getPostTime(props.nextPost));
const parentTime = computed(() => getPostTime(props.parentPost));
// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
const currentTime = computed(() => getPostTime(props.currentPost)!);

const previousDateTime = computed(() =>
    undefinedOr(previousTime.value, i => DateTime.fromSeconds(i)));
const nextDateTime = computed(() =>
    undefinedOr(nextTime.value, i => DateTime.fromSeconds(i)));
const parentDateTime = computed(() =>
    undefinedOr(parentTime.value, i => DateTime.fromSeconds(i)));
const currentDateTime = computed(() => DateTime.fromSeconds(currentTime.value));
</script>

<style scoped>
.post-badge-time-current-full {
    width: 12rem;
}
</style>
