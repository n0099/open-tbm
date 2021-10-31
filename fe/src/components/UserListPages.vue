<template>
    <user-list v-for="(usersData, currentListPage) in userPages"
               :key="`page${currentListPage + 1}@${JSON.stringify({ ...$route.params, ...$route.query })}`"
               :users-data="usersData"
               :loading-new-users="loadingNewUsers"
               :is-last-page-in-pages="currentListPage === userPages.length - 1"></user-list>
    <PlaceholderLoadingList v-if="loadingNewUsers" />
</template>

<script lang="ts">
import PlaceholderLoadingList from '@/components/PlaceholderLoadingList.vue';

import { defineComponent, onMounted, reactive, toRefs, watch } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import Noty from 'noty';

export default defineComponent({
    components: { PlaceholderLoadingList },
    setup() {
        const route = useRoute();
        const state = reactive({
            userPages: [],
            loadingNewUsers: false
        });
        const queryUsersData = (route: RouteLocationNormalizedLoaded) => {
            const ajaxStartTime = Date.now();
            const ajaxQueryString = { ...route.params, ...route.query }; // deep clone
            if (_.isEmpty(ajaxQueryString)) {
                new Noty({ timeout: 3000, type: 'warning', text: '请输入用户查询参数' }).show();
                this.$parent.showFirstLoadingPlaceholder = false;
                return;
            }
            state.loadingNewUsers = true;
            $$reCAPTCHACheck().then(reCAPTCHA => {
                $.getJSON(`${$$baseUrl}/api/usersQuery`, $.param({ ...ajaxQueryString, reCAPTCHA }))
                    .done(ajaxData => {
                        state.userPages = [ajaxData];
                        new Noty({ timeout: 3000, type: 'success', text: `已加载第${ajaxData.pages.currentPage}页 ${ajaxData.pages.currentItems}条记录 耗时${Date.now() - ajaxStartTime}ms` }).show();
                    })
                    .fail(jqXHR => {
                        state.userPages = [];
                        this.$parent.showError404Placeholder = true;
                    })
                    .always(() => state.loadingNewUsers = false);
            });
        };

        watch(() => state.loadingNewUsers, loadingNewUsers => {
            if (loadingNewUsers) {
                this.$parent.showError404Placeholder = false;
                this.$parent.showFirstLoadingPlaceholder = false;
            }
        });
        onMounted(() => { queryUsersData(route) });
        onBeforeRouteUpdate(to => { queryUsersData(to) });

        return { ...toRefs(state) };
    }
});
</script>
