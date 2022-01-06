<template>
    <UserQueryForm :query="$route.query" :params="params" :selectUserBy="selectUserBy" class="my-4" />
    <UserList v-for="(usersQuery, pageIndex) in userPages"
              :key="`page${usersQuery.pages.currentPage}`"
              :id="`page${usersQuery.pages.currentPage}`"
              :usersData="usersQuery"
              :isLoadingNewPage="isLoading"
              :isLastPageInPages="pageIndex === userPages.length - 1" />
    <PlaceholderError v-if="lastFetchError !== null" :error="lastFetchError" />
    <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
</template>

<script lang="ts">
import type { SelectTiebaUserBy, SelectTiebaUserParams } from '@/components/SelectTiebaUser.vue';
import PlaceholderError from '@/components/PlaceholderError.vue';
import PlaceholderPostList from '@/components/PlaceholderPostList.vue';
import UserList from '@/components/UserList.vue';
import UserQueryForm from '@/components/UserQueryForm.vue';
import { apiUsersQuery, isApiError } from '@/api';
import type { ApiError, ApiUsersQuery } from '@/api/index.d';
import { notyShow, removeEnd, removeStart } from '@/shared';
import { compareRouteIsNewQuery, setComponentCustomScrollBehaviour } from '@/router';

import { defineComponent, reactive, toRefs, watchEffect } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import _ from 'lodash';

export default defineComponent({
    components: { UserList, UserQueryForm, PlaceholderError, PlaceholderPostList },
    props: {
        page: String,
        uid: String,
        name: String,
        displayName: String
    },
    setup(props) {
        const route = useRoute();
        const state = reactive<{
            params: Pick<SelectTiebaUserParams, Exclude<SelectTiebaUserBy, '' | 'displayNameNULL' | 'nameNULL'>>,
            selectUserBy: SelectTiebaUserBy,
            userPages: ApiUsersQuery[],
            isLoading: boolean,
            lastFetchError: ApiError | null,
            showPlaceholderPostList: boolean
        }>({
            params: {},
            selectUserBy: '',
            userPages: [],
            isLoading: false,
            lastFetchError: null,
            showPlaceholderPostList: false
        });
        const fetchUsersData = async (_route: RouteLocationNormalizedLoaded, isNewQuery: boolean) => {
            const startTime = Date.now();
            const queryString = { ..._route.params, ..._route.query };
            state.lastFetchError = null;
            state.showPlaceholderPostList = true;
            if (isNewQuery) state.userPages = [];
            if (_.isEmpty(queryString)) {
                notyShow('warning', '请输入用户查询参数');
                return false;
            }
            state.isLoading = true;
            const usersQuery = await apiUsersQuery(queryString).finally(() => {
                state.showPlaceholderPostList = false;
                state.isLoading = false;
            });
            if (isApiError(usersQuery)) {
                state.lastFetchError = usersQuery;
                return false;
            }
            if (isNewQuery) state.userPages = [usersQuery];
            else state.userPages = _.sortBy([...state.userPages, usersQuery], i => i.pages.currentPage);
            notyShow('success', `已加载第${usersQuery.pages.currentPage}页 ${usersQuery.pages.itemsCount}条记录 耗时${Date.now() - startTime}ms`);
            return true;
        };
        fetchUsersData(route, true);

        watchEffect(() => {
            state.selectUserBy = removeStart(removeEnd(route.name?.toString() ?? '', '+p'), 'user/') as SelectTiebaUserBy;
            state.params = { ..._.omit(props, 'page'), uid: props.uid === undefined ? undefined : Number(props.uid) };
        });

        onBeforeRouteUpdate(async (to, from) => {
            const isNewQuery = compareRouteIsNewQuery(to, from);
            if (!isNewQuery && !_.isEmpty(_.filter(
                state.userPages,
                i => i.pages.currentPage === Number(to.params.page ?? 1)
            ))) return true;
            const isFetchSuccess = await fetchUsersData(to, isNewQuery);
            return isNewQuery ? true : isFetchSuccess;
        });
        setComponentCustomScrollBehaviour((to, from) => {
            if (!compareRouteIsNewQuery(to, from)
                && ('page' in from.params || 'page' in to.params)
            ) return { el: `#page${to.params.page ?? 1}`, top: 0 };
            return undefined;
        });

        return { ...toRefs(state) };
    }
});
</script>
