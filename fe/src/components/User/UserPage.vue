<template>
<div>
    <PageCurrentButton :currentCursor="users.pages.currentCursor" />
    <div v-for="(user, userIndex) in users.users" :key="user.uid" class="row" :id="user.uid.toString()">
        <div class="col-3">
            <img
                :src="toUserPortraitImageUrl(user.portrait)" loading="lazy"
                class="d-block mx-auto badge bg-light" width="110" height="110" />
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
    <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages" :nextCursorRoute="nextCursorRoute" />
</div>
</template>

<script setup lang="ts">
// @ts-nocheck
const props = defineProps<{
    users: ApiUsers['response'],
    isLoadingNewPage: boolean,
    isLastPageInPages: boolean
}>();
const nextCursorRoute = useNextCursorRoute(props.users.pages.nextCursor);

const userGender = (gender: UserGender) => {
    const genderDescription = {
        /* eslint-disable @typescript-eslint/naming-convention */
        0: '未指定（显示为男）',
        1: '男 ♂',
        2: '女 ♀'
        /* eslint-enable @typescript-eslint/naming-convention */
    } as const;

    return gender === null ? 'NULL' : genderDescription[gender];
};
</script>
