<template>
    <div :id="id" class="p-2 row align-items-center">
        <div class="col align-middle"><hr /></div>
        <div v-for="(page, _i) in [usersData.pages]" :key="_i" class="w-auto">
            <div class="p-2 badge bg-light text-dark">
                <RouterLink v-if="page.currentPage > 1" :to="pagesRoute.prev" class="badge bg-primary">上一页</RouterLink>
                <p class="h4">第 {{ page.currentPage }} 页</p>
                <span class="small">{{ `第 ${page.firstItem}~${page.firstItem + page.currentItems - 1} 条` }}</span>
            </div>
        </div>
        <div class="col align-middle"><hr /></div>
    </div>
    <div v-for="(user, userIndex) in usersData.users" :key="user.uid" :id="user.uid" class="row">
        <div class="col-3">
            <img class="lazyload d-block mx-auto badge bg-light" width="110" height="110"
                 :data-src="tiebaUserPortraitUrl(user.avatarUrl)" />
        </div>
        <div class="col">
            <p>百度UID：{{ user.uid }}</p>
            <p v-if="user.displayName !== null">覆盖ID：{{ user.displayName }}</p>
            <p v-if="user.name !== null">用户名：{{ user.name }}</p>
            <p>性别：{{ userGender(user.gender) }}</p>
            <p v-if="user.fansNickname !== null">粉丝头衔：{{ user.fansNickname }}</p>
        </div>
        <div v-if="userIndex !== usersData.users.length - 1" class="w-100"><hr /></div>
    </div>
    <div v-if="!isLoadingNewPage && isLastPageInPages" class="p-4">
        <div class="row align-items-center">
            <div class="col"><hr /></div>
            <div class="w-auto" v-for="(page, _i) in [usersData.pages]" :key="_i">
                <RouterLink :to="pagesRoute.next" class="btn btn-secondary">
                    <span class="h4">下一页</span>
                </RouterLink>
            </div>
            <div class="col"><hr /></div>
        </div>
    </div>
</template>

<script lang="ts">
import type { ApiUsersQuery, TiebaUserGender } from '@/api/index.d';
import { routeNameStrAssert, tiebaUserPortraitUrl } from '@/shared';

import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useRoute } from 'vue-router';
import _ from 'lodash';

export default defineComponent({
    props: {
        id: String,
        usersData: { type: Object as PropType<ApiUsersQuery>, required: true },
        isLoadingNewPage: { type: Boolean, required: true },
        isLastPageInPages: { type: Boolean, required: true }
    },
    setup() {
        const route = useRoute();
        const userGender = (gender: TiebaUserGender) => {
            const gendersList = {
                0: '未指定（显示为男）',
                1: '男 ♂',
                2: '女 ♀'
            } as const;
            return gender === null ? 'NULL' : gendersList[gender];
        };

        routeNameStrAssert(route.name);
        // do not use _.merge(route, ...), https://github.com/vuejs/vue-router-next/issues/1184
        // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
        const currentRoutePage = Number(route.params?.page ?? 1);
        const routeNameWithPage = _.endsWith(route.name, '+p') ? route.name : `${route.name}+p`;
        const pagesRoute: { [P in 'next' | 'prev']: RouteLocationRaw } = {
            prev: { ...route, name: routeNameWithPage, params: { page: currentRoutePage - 1 } },
            next: { ...route, name: routeNameWithPage, params: { page: currentRoutePage + 1 } }
        };

        return { tiebaUserPortraitUrl, userGender, pagesRoute };
    }
});
</script>

<style scoped>
.user-item-enter-active, .user-item-leave-active {
    transition: opacity .3s;
}
.user-item-enter, .user-item-leave-to {
    opacity: 0;
}
</style>
