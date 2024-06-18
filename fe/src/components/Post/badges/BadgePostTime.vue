<template>
    <DefineTemplate v-slot="{ $slots, base, relativeTo }">
        <span :data-tippy-content="`
                本${postType}${timestampType}：<br>
                ${currentDateTime.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
                ${base === undefined || relativeTo === undefined
              ? ''
              : `${relativeTo}：<br>
                ${base.toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br>
                相差 ${currentDateTime.diff(base).rescale().toHuman()}`}
                `"
              class="ms-1 fw-normal badge rounded-pill" v-bind="$attrs">
            <component :is="$slots.default" />
            {{ currentDateTime.toRelative({ base, round: false }) }}
        </span>
    </DefineTemplate>
    <ReuseTemplate v-if="previousTime !== undefined && previousTime < currentTime"
                   @mouseenter="() => props.previousPost !== undefined
                       && highlightPostStore.set(props.previousPost, props.currentPostIDKey)"
                   @mouseleave="() => highlightPostStore.unset()"
                   :base="previousDateTime" :relativeTo="`相对于上一${postType}${timestampType}`">
        <FontAwesomeIcon :icon="faChevronUp" class="align-bottom" />
    </ReuseTemplate>
    <ReuseTemplate v-else-if="nextTime !== undefined && nextTime < currentTime"
                   @mouseenter="() => props.nextPost !== undefined
                       && highlightPostStore.set(props.nextPost, props.currentPostIDKey)"
                   @mouseleave="() => highlightPostStore.unset()"
                   :base="nextDateTime" :relativeTo="`相对于下一${postType}${timestampType}`">
        <FontAwesomeIcon :icon="faChevronDown" class="align-bottom" />
    </ReuseTemplate>
    <ReuseTemplate v-else-if="parentTime !== undefined && parentTime !== currentTime"
                   @mouseenter="() => props.parentPost !== undefined && props.parentPostIDKey !== undefined
                       && highlightPostStore.set(props.parentPost, props.parentPostIDKey)"
                   @mouseleave="() => highlightPostStore.unset()"
                   :base="parentDateTime"
                   :relativeTo="`相对于所属${postTypeText[postTypeText.indexOf(props.postType) - 1]}${timestampType}`">
        <FontAwesomeIcon :icon="faAnglesUp" class="align-bottom" />
    </ReuseTemplate>
    <ReuseTemplate />
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
import type { Reply, SubReply, Thread } from '@/api/post';
import type { Post, PostIDOf, PostTypeTextOf } from '@/utils';
import { useHighlightPostStore } from '@/stores/highlightPost';
import { createReusableTemplate } from '@vueuse/core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
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
const [DefineTemplate, ReuseTemplate] = createReusableTemplate<{
    base?: DateTime<true>,
    relativeTo?: string
}>();

const previousTime = computed(() =>
    (props.previousPost?.[props.postTimeKey] as TPostTimeValue | undefined));
const nextTime = computed(() =>
    (props.nextPost?.[props.postTimeKey] as TPostTimeValue | undefined));
const parentTime = computed(() =>
    (props.parentPost?.[props.postTimeKey] as TPostTimeValue | undefined));
const currentTime = computed(() =>
    (props.currentPost[props.postTimeKey] as TPostTimeValue));

const previousDateTime = computed(() =>
    undefinedOr(previousTime.value, i => DateTime.fromSeconds(i)));
const nextDateTime = computed(() =>
    undefinedOr(nextTime.value, i => DateTime.fromSeconds(i)));
const parentDateTime = computed(() =>
    undefinedOr(parentTime.value, i => DateTime.fromSeconds(i)));
const currentDateTime = computed(() => DateTime.fromSeconds(currentTime.value));
</script>

<style scoped>
span {
    padding-inline-start: .75rem;
    padding-inline-end: .75rem;
}
</style>
