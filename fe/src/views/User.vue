<template>
    <UserQueryForm :query="$route.query" :params="params" :selectUserBy="selectUserBy" class="my-4" />
    <UserList v-for="(usersQuery, pageIndex) in userPages"
              :key="'page' + usersQuery.pages.currentPage"
              :id="'page' + usersQuery.pages.currentPage"
              :usersData="usersQuery"
              :isLoadingNewPage="isLoading"
              :isLastPageInPages="pageIndex === userPages.length - 1" />
    <PlaceholderError v-show="showPlaceholderError" :error="lastFetchError" />
    <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
</template>

<script lang="ts">
import { notyShow } from '@/shared';
import { apiUsersQuery, isApiError } from '@/api/index';
import type { ApiError, ApiUsersQuery } from '@/api/index.d';
import PlaceholderError from '@/components/PlaceholderError.vue';
import PlaceholderPostList from '@/components/PlaceholderPostList.vue';
import type { SelectTiebaUserBy, SelectTiebaUserParams } from '@/components/SelectTiebaUser.vue';
import UserList from '@/components/UserList.vue';
import UserQueryForm from '@/components/UserQueryForm.vue';

import { defineComponent, onMounted, reactive, toRefs, watchEffect } from 'vue';
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
            showPlaceholderError: boolean,
            showPlaceholderPostList: boolean
        }>({
            params: {},
            selectUserBy: '',
            userPages: [],
            isLoading: false,
            lastFetchError: null,
            showPlaceholderError: false,
            showPlaceholderPostList: false
        });
        const fetchUsersData = async (_route: RouteLocationNormalizedLoaded, isNewQuery: boolean) => {
            const startTime = Date.now();
            const queryString = { ..._route.params, ..._route.query };
            state.lastFetchError = null;
            state.showPlaceholderPostList = true;
            state.showPlaceholderError = false;
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
                state.showPlaceholderError = true;
                state.lastFetchError = usersQuery;
                return false;
            }
            if (isNewQuery) state.userPages = [usersQuery];
            else state.userPages = _.sortBy([...state.userPages, usersQuery], i => i.pages.currentPage);
            notyShow('success', `已加载第${usersQuery.pages.currentPage}页 ${usersQuery.pages.currentItems}条记录 耗时${Date.now() - startTime}ms`);
            return true;
        };

        watchEffect(() => {
            state.selectUserBy = _.trimEnd(route.name?.toString(), '+p') as SelectTiebaUserBy;
            state.params = { ..._.omit(props, 'page'), uid: props.uid === undefined ? undefined : Number(props.uid) };
        });
        onMounted(() => { fetchUsersData(route, true) });
        onBeforeRouteUpdate(async (to, from) => {
            const isNewQuery = !(_.isEqual(to.query, from.query)
                && _.isEqual(_.omit(to.params, 'page'), _.omit(from.params, 'page')));
            if (!isNewQuery && !_.isEmpty(_.filter(state.userPages, i => i.pages.currentPage === Number(to.params.page)))) {
                document.getElementById(`page${to.params.page}`)?.scrollIntoView();
                return false;
            }
            const isFetchSuccess = await fetchUsersData(to, isNewQuery);
            return isNewQuery ? true : isFetchSuccess;
        });

        return { ...toRefs(state) };
    }
});
</script>
