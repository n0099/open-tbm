<template>
<NuxtLink :to="toUserRoute(authorUid)" noPrefetch class="fs-.75">
    <span
        v-if="latestReplier?.uid !== authorUid"
        class="fw-normal link-success">楼主：</span>
    <span v-else class="fw-normal link-info">楼主兼最后回复：</span>
    <span class="fw-bold link-dark">{{ renderUsername(authorUid) }}</span>
</NuxtLink>
<PostBadgeUser
    v-if="authorUser.currentForumModerator !== null"
    :user="authorUser" class="fs-.75 ms-1" />
<template v-if="latestReplier?.uid === undefined">
    <span class="fs-.75">
        <span class="ms-2 fw-normal link-secondary">最后回复：</span>
        <span class="fw-bold link-dark">未知用户</span>
    </span>
</template>
<template v-else-if="latestReplier.uid === null">
    <span
        v-if="latestReplier !== undefined
            && !(latestReplier.name === null && latestReplier.displayName === null)"
        class="fs-.75">
        <span class="ms-2 fw-normal link-secondary">最后回复：</span>
        <NuxtLink
            v-if="latestReplier.name !== null"
            :to="{ name: 'users/name', params: _.pick(latestReplier, 'name') }"
            noPrefetch class="fw-bold link-dark">
            {{ latestReplier.name }}
        </NuxtLink>
        <template v-if="latestReplier.displayName !== null">
            <span>&nbsp;</span>
            <NuxtLink
                :to="{ name: 'users/displayName', params: _.pick(latestReplier, 'displayName') }"
                noPrefetch class="fw-bold link-dark">
                {{ latestReplier.displayName }}
            </NuxtLink>
        </template>
    </span>
</template>
<template v-else-if="latestReplier.uid !== authorUid">
    <NuxtLink :to="toUserRoute(latestReplier.uid)" noPrefetch class="fs-.75 ms-2">
        <span class="ms-2 fw-normal link-secondary">最后回复：</span>
        <span class="fw-bold link-dark">{{ renderUsername(latestReplier.uid) }}</span>
    </NuxtLink>
    <PostBadgeUser
        v-if="!_.isNil(latestReplierUser?.currentForumModerator)"
        :user="latestReplierUser" class="fs-.75 ms-1" />
</template>
</template>

<script setup lang="ts">
import _ from 'lodash';

const props = defineProps<{ thread: Thread }>();
const { getUser, renderUsername, getLatestReplier } = useUserProvision().inject();
const authorUid = computed(() => props.thread.authorUid);
const authorUser = computed(() => getUser.value(authorUid.value));
const latestReplier = computed(() => getLatestReplier.value(props.thread.latestReplierId));
const latestReplierUser = computed(() => (
    _.isNil(latestReplier.value?.uid) ? undefined : getUser.value(latestReplier.value.uid)));
</script>

<style scoped>
.fs-\.75 {
    font-size: .75rem;
}
</style>
