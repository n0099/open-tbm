<template>
<NuxtLink
    v-for="(user, index) in users" :key="user.name" :to="user.route"
    :class="{ 'ms-1': users.length > 1 && index !== 0 }"
    noPrefetch class="fw-bold link-dark">
    {{ user.name }}
</NuxtLink>
</template>

<script lang="ts">
import type { LocationAsRelativeRaw } from 'vue-router';
import _ from 'lodash';

type LatestReplierWithoutUid = LatestReplier & { uid: null };
type UsernamesWithRoute = Array<{ name: string, route: LocationAsRelativeRaw }>;
export const expandLatestReplierToRoutes = (latestReplier: LatestReplierWithoutUid): UsernamesWithRoute => _.reduce<
    Pick<LatestReplierWithoutUid, 'name' | 'displayName'>,
    NonNullable<UsernamesWithRoute>
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

<script setup lang="ts">
defineProps<{ users: UsernamesWithRoute }>();
</script>
