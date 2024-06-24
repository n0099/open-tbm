<template>
<ClientOnly>
    <BadgePostTimeView
        v-if="previousTime !== undefined && previousTime < currentTime"
        @mouseenter="() => props.previousPost !== undefined
            && highlightPostStore.set(props.previousPost, props.currentPostIDKey)"
        @mouseleave="() => highlightPostStore.unset()"
        :current="currentDateTime" :relativeTo="previousDateTime"
        :relativeToText="`相对于上一${postType}${timestampType}`"
        :postType="props.postType" :timestampType="props.timestampType" v-bind="$attrs">
        <FontAwesome :icon="faChevronUp" class="align-bottom" />
    </BadgePostTimeView>
    <BadgePostTimeView
        v-else-if="nextTime !== undefined && nextTime < currentTime"
        @mouseenter="() => props.nextPost !== undefined
            && highlightPostStore.set(props.nextPost, props.currentPostIDKey)"
        @mouseleave="() => highlightPostStore.unset()"
        :current="currentDateTime" :relativeTo="nextDateTime"
        :relativeToText="`相对于下一${postType}${timestampType}`"
        :postType="props.postType" :timestampType="props.timestampType" v-bind="$attrs">
        <FontAwesome :icon="faChevronDown" class="align-bottom" />
    </BadgePostTimeView>
    <BadgePostTimeView
        v-else-if="parentTime !== undefined && parentTime !== currentTime"
        @mouseenter="() => props.parentPost !== undefined
            && props.parentPostIDKey !== undefined
            && highlightPostStore.set(props.parentPost, props.parentPostIDKey)"
        @mouseleave="() => highlightPostStore.unset()"
        :current="currentDateTime" :relativeTo="parentDateTime"
        :relativeToText="`相对于所属${postTypeText[postTypeText.indexOf(props.postType) - 1]}${timestampType}`"
        :postType="props.postType" :timestampType="props.timestampType" v-bind="$attrs">
        <FontAwesome :icon="faAnglesUp" class="align-bottom" />
    </BadgePostTimeView>
</ClientOnly>
<BadgePostTimeView
    :current="currentDateTime" :postType="props.postType"
    :timestampType="props.timestampType" v-bind="$attrs" />
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

// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
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
