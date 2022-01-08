<template>
    <div>
        <PagePrevButton :pageInfo="usersData.pages" :pageRoutes="pageRoutes" />
        <div v-for="(user, userIndex) in usersData.users" :key="user.uid" :id="user.uid" class="row">
            <div class="col-3">
                <img :data-src="tiebaUserPortraitUrl(user.avatarUrl)"
                     class="lazyload d-block mx-auto badge bg-light" width="110" height="110" />
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
        <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages" :pageRoutes="pageRoutes" />
    </div>
</template>

<script lang="ts">
import PageNextButton from '@/components/PageNextButton.vue';
import PagePrevButton from '@/components/PagePrevButton.vue';
import type { ApiUsersQuery, TiebaUserGender } from '@/api/index.d';
import { removeEnd, tiebaUserPortraitUrl } from '@/shared';
import { assertRouteNameIsStr } from '@/router';
import type { ComputedRef, PropType } from 'vue';
import { computed, defineComponent } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useRoute } from 'vue-router';
import _ from 'lodash';

export default defineComponent({
    components: { PageNextButton, PagePrevButton },
    props: {
        usersData: { type: Object as PropType<ApiUsersQuery>, required: true },
        isLoadingNewPage: { type: Boolean, required: true },
        isLastPageInPages: { type: Boolean, required: true }
    },
    setup(props) {
        const route = useRoute();
        const userGender = (gender: TiebaUserGender) => {
            const gendersList = {
                0: '未指定（显示为男）',
                1: '男 ♂',
                2: '女 ♀'
            } as const;
            return gender === null ? 'NULL' : gendersList[gender];
        };

        const pageRoutes: ComputedRef<{ [P in 'next' | 'prev']: RouteLocationRaw }> = computed(() => {
            assertRouteNameIsStr(route.name);
            const { currentPage } = props.usersData.pages;
            const routeNameWithPage = _.endsWith(route.name, '+p') ? route.name : `${route.name}+p`;
            const { query } = route;
            return {
                prev: currentPage - 1 === 1
                    ? { query, name: removeEnd(route.name, '+p') } // prevent '/page/1' occurs in route path
                    : { query, name: routeNameWithPage, params: { page: currentPage - 1 } },
                next: { query, name: routeNameWithPage, params: { page: currentPage + 1 } }
            };
        });

        return { tiebaUserPortraitUrl, userGender, pageRoutes };
    }
});
</script>
