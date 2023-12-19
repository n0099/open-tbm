<template>
    <UserQueryForm :query="$route.query" :params="params" :selectUserBy="selectUserBy" class="my-4" />
    <UsersPage v-for="(users, pageIndex) in userPages"
               :key="`page${users.pages.currentCursor}`"
               :users="users"
               :isLoadingNewPage="isLoading"
               :isLastPageInPages="pageIndex === userPages.length - 1"
               :id="`page${users.pages.currentCursor}`" />
    <PlaceholderError v-if="lastFetchError !== null" :error="lastFetchError" class="border-top" />
    <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
</template>

<script setup lang="ts">
import PlaceholderError from '@/components/placeholders/PlaceholderError.vue';
import PlaceholderPostList from '@/components/placeholders/PlaceholderPostList.vue';
import UsersPage from '@/components/User/UsersPage.vue';
import UserQueryForm from '@/components/User/QueryForm.vue';
import type { SelectTiebaUserBy, SelectTiebaUserParams } from '@/components/widgets/SelectTiebaUser.vue';

import { apiUsersQuery, isApiError } from '@/api';
import type { ApiError, ApiUsersQuery } from '@/api/index.d';
import { notyShow, removeEnd, removeStart, titleTemplate } from '@/shared';
import { compareRouteIsNewQuery, getRouteCursorParam, routeNameSuffix, setComponentCustomScrollBehaviour } from '@/router';

import { nextTick, onBeforeMount, ref, watchEffect } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import { useHead } from '@unhead/vue';
import _ from 'lodash';

const props = defineProps<{
    page: string,
    uid: string,
    name: string,
    displayName: string
}>();
const route = useRoute();
useHead({ title: titleTemplate('用户查询') });
const params = ref<Pick<SelectTiebaUserParams, Exclude<SelectTiebaUserBy, '' | 'displayNameNULL' | 'nameNULL'>>>({});
const selectUserBy = ref<SelectTiebaUserBy>('');
const userPages = ref<ApiUsersQuery[]>([]);
const isLoading = ref<boolean>(false);
const lastFetchError = ref<ApiError | null>(null);
const showPlaceholderPostList = ref<boolean>(false);

const fetchUsersData = async (_route: RouteLocationNormalizedLoaded, isNewQuery: boolean) => {
    const startTime = Date.now();
    const queryString = { ..._route.params, ..._route.query };
    lastFetchError.value = null;
    showPlaceholderPostList.value = true;
    if (isNewQuery)
        userPages.value = [];
    if (_.isEmpty(queryString)) {
        notyShow('warning', '请输入用户查询参数');

        return false;
    }
    isLoading.value = true;
    const usersQuery = await apiUsersQuery(queryString).finally(() => {
        showPlaceholderPostList.value = false;
        isLoading.value = false;
    });
    if (isApiError(usersQuery)) {
        lastFetchError.value = usersQuery;

        return false;
    }
    if (isNewQuery)
        userPages.value = [usersQuery];
    else
        userPages.value = _.sortBy([...userPages.value, usersQuery], i => i.pages.currentCursor);
    const networkTime = Date.now() - startTime;
    await nextTick(); // wait for child components finish dom update
    notyShow('success', `已加载第${usersQuery.pages.currentCursor}页`
        + ` 耗时${((Date.now() - startTime) / 1000).toFixed(2)}s 网络${networkTime}ms`);

    return true;
};
onBeforeMount(async () => fetchUsersData(route, true));

watchEffect(() => {
    selectUserBy.value = removeStart(removeEnd(
        String(route.name ?? ''),
        routeNameSuffix.page
    ), 'user/') as SelectTiebaUserBy;
    params.value = { ..._.omit(props, 'page'), uid: Number(props.uid) };
});
onBeforeRouteUpdate(async (to, from) => {
    const isNewQuery = compareRouteIsNewQuery(to, from);
    if (!(isNewQuery || _.isEmpty(_.filter(
        userPages.value,
        i => i.pages.currentCursor === getRouteCursorParam(to)
    ))))
        return true;
    const isFetchSuccess = await fetchUsersData(to, isNewQuery);

    return isNewQuery ? true : isFetchSuccess; // only pass pending route update after successful fetched
});
setComponentCustomScrollBehaviour((to, from) => {
    if (!compareRouteIsNewQuery(to, from))
        return { el: `#page${getRouteCursorParam(to)}` };
});
</script>
