<template>
    <div class="container">
        <UserQueryForm :query="$route.query" :params="params" :selectUserBy="selectUserBy" class="my-4" />
        <UserPage v-for="(users, pageIndex) in userPages"
                  :key="`page${users.pages.currentCursor}`"
                  :users="users"
                  :isLoadingNewPage="isLoading"
                  :isLastPageInPages="pageIndex === userPages.length - 1"
                  :id="`page${users.pages.currentCursor}`" />
        <PlaceholderError v-if="lastFetchError !== null" :error="lastFetchError" class="border-top" />
        <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
    </div>
</template>

<script setup lang="ts">

import _ from 'lodash';

const props = defineProps<{
    page: string,
    uid: string,
    name: string,
    displayName: string
}>();
const route = useRoute();
useHead({ title: '用户查询' });
const params = ref<Pick<SelectUserParams, Exclude<SelectUserBy, '' | 'displayNameNULL' | 'nameNULL'>>>({});
const selectUserBy = ref<SelectUserBy>('');
const userPages = ref<Array<ApiUsers['response']>>([]);
const isLoading = ref(false);
const lastFetchError = ref<ApiError | null>(null);
const showPlaceholderPostList = ref(false);

const fetchUsers = async (_route: RouteLocationNormalized, isNewQuery: boolean) => {
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
    const query = await apiUsers(queryString).finally(() => {
        showPlaceholderPostList.value = false;
        isLoading.value = false;
    });
    if (isApiError(query)) {
        lastFetchError.value = query;

        return false;
    }
    userPages.value = isNewQuery
        ? [query]
        : _.sortBy([...userPages.value, query], i => i.pages.currentCursor);
    const networkTime = Date.now() - startTime;
    await nextTick(); // wait for child components finish dom update
    notyShow('success', `已加载第${query.pages.currentCursor}页`
        + ` 耗时${((Date.now() - startTime) / 1000).toFixed(2)}s 网络${networkTime}ms`);

    return true;
};
onBeforeMount(async () => fetchUsers(route, true));

watchEffect(() => {
    selectUserBy.value = removeStart(removeEnd(
        route.name?.toString() ?? '',
        routeNameSuffix.page
    ), 'users/') as SelectUserBy;
    params.value = { ..._.omit(props, 'cursor'), uid: Number(props.uid) };
});
onBeforeRouteUpdate(async (to, from) => {
    const isNewQuery = compareRouteIsNewQuery(to, from);
    if (!(isNewQuery || _.isEmpty(_.filter(
        userPages.value,
        i => i.pages.currentCursor === getRouteCursorParam(to)
    ))))
        return true;
    const isFetchSuccess = await fetchUsers(to, isNewQuery);

    return isNewQuery ? true : isFetchSuccess; // only pass pending route update after successful fetched
});
useRouteScrollBehaviorStore().set((to, from): ReturnType<RouterScrollBehavior> => {
    if (!compareRouteIsNewQuery(to, from))
        return { el: `#page${getRouteCursorParam(to)}` };

    return undefined;
});
</script>
