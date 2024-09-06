<template>
<span>
    <span
        v-if="latestReplier?.uid !== authorUid"
        class="fw-normal link-success">楼主：</span>
    <span v-else class="fw-normal link-info">楼主兼最后回复：</span>
    <NuxtLink :to="toUserRoute(authorUid)" noPrefetch class="fw-bold link-dark">
        {{ renderUsername(authorUid) }}
    </NuxtLink>
</span>
<PostBadgeUser
    v-if="authorUser.currentForumModerator !== null"
    :user="authorUser" class="ms-1 user-badge" />
<DefineLatestReplier v-slot="{ users }">
    <span class="ms-2">
        <span class="fw-normal link-secondary">最后回复：</span>
        <template v-if="users !== undefined">
            <NuxtLink
                v-for="(user, index) in users" :key="user.name" :to="user.route"
                :class="{ 'ms-1': users.length > 1 && index !== 0 }"
                noPrefetch class="fw-bold link-dark">
                {{ user.name }}
            </NuxtLink>
        </template>
        <span v-else class="fw-bold link-dark">未知用户</span>
    </span>
</DefineLatestReplier>
<ReuseLatestReplier v-if="latestReplier?.uid === undefined" />
<ReuseLatestReplier
    v-else-if="latestReplier.uid === null
        && latestReplier !== undefined
        && !(latestReplier.name === null && latestReplier.displayName === null)"
    :users="toReuseLatestReplierUsersProp(latestReplier)" />
<template v-else-if="latestReplier.uid !== null && latestReplier.uid !== authorUid">
    <ReuseLatestReplier :users="[{ name: renderUsername(latestReplier.uid), route: toUserRoute(latestReplier.uid) }]" />
    <PostBadgeUser
        v-if="!_.isNil(latestReplierUser?.currentForumModerator)"
        :user="latestReplierUser" class="ms-1 user-badge" />
</template>
</template>

<script setup lang="ts">
import type { LocationAsRelativeRaw } from 'vue-router';
import _ from 'lodash';

const { thread } = defineProps<{ thread: Thread }>();
const { getUser, renderUsername, getLatestReplier } = usePostPageProvision().inject();

const authorUid = computed(() => thread.authorUid);
const authorUser = computed(() => getUser.value(authorUid.value));
const latestReplier = computed(() => getLatestReplier.value(thread.latestReplierId));
const latestReplierUser = computed(() => (
    _.isNil(latestReplier.value?.uid) ? undefined : getUser.value(latestReplier.value.uid)));

interface ReuseLatestReplierProps { users?: Array<{ name: string, route: LocationAsRelativeRaw }> }
const [DefineLatestReplier, ReuseLatestReplier] = createReusableTemplate<ReuseLatestReplierProps>();
const toReuseLatestReplierUsersProp = (latestReplier: LatestReplier): ReuseLatestReplierProps['users'] => _.reduce<
    Pick<LatestReplier, 'name' | 'displayName'>,
    NonNullable<ReuseLatestReplierProps['users']>
>(
    _.pick(latestReplier, 'name', 'displayName'),
    (acc, name, type) => {
        if (name !== null)
            acc.push({ name, route: { name: `users/${type}`, params: { [type]: name } } });

        return acc;
    },
    []
);
</script>

<style scoped>
.user-badge {
    vertical-align: text-top;
}
</style>
