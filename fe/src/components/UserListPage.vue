<template>
    <div>
        <PagePrevButton :page="users.pages" :pageRoutes="pageRoutes" />
        <div v-for="(user, userIndex) in users.users" :key="user.uid" :id="String(user.uid)" class="row">
            <div class="col-3">
                <img :data-src="toTiebaUserPortraitImageUrl(user.portrait)"
                     class="lazy d-block mx-auto badge bg-light" width="110" height="110" />
            </div>
            <div class="col">
                <p>百度UID：{{ user.uid }}</p>
                <p v-if="user.displayName !== null">覆盖ID：{{ user.displayName }}</p>
                <p v-if="user.name !== null">用户名：{{ user.name }}</p>
                <p>性别：{{ userGender(user.gender) }}</p>
                <p v-if="user.fansNickname !== null">粉丝头衔：{{ user.fansNickname }}</p>
            </div>
            <div v-if="userIndex !== users.users.length - 1" class="w-100"><hr /></div>
        </div>
        <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages" :pageRoutes="pageRoutes" />
    </div>
</template>

<script setup lang="ts">
import { PageNextButton, PagePrevButton, usePageRoutes } from './usePageNextAndPrevButton';
import type { TiebaUserGender } from '@/api/user';
import type { ApiUsersQuery } from '@/api/index.d';
import { lazyLoadUpdate } from '@/shared/lazyLoad';
import { toTiebaUserPortraitImageUrl } from '@/shared';
import { watch } from 'vue';

const props = defineProps<{
    users: ApiUsersQuery,
    isLoadingNewPage: boolean,
    isLastPageInPages: boolean
}>();
const pageRoutes = usePageRoutes(props.users.pages.currentPage);

const userGender = (gender: TiebaUserGender) => {
    const gendersList = {
        /* eslint-disable @typescript-eslint/naming-convention */
        0: '未指定（显示为男）',
        1: '男 ♂',
        2: '女 ♀'
        /* eslint-enable @typescript-eslint/naming-convention */
    } as const;
    return gender === null ? 'NULL' : gendersList[gender];
};
watch(() => props.users, lazyLoadUpdate);
</script>
