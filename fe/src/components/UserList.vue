<template>
    <div class="reply-list-previous-page p-2 row align-items-center">
        <div class="col align-middle"><hr /></div>
        <div v-for="page in [usersData.pages]" class="w-auto">
            <div class="p-2 badge badge-light">
                <a v-if="page.currentPage > 1" class="badge badge-primary" :href="previousPageUrl">上一页</a>
                <p class="h4">第 {{ page.currentPage }} 页</p>
                <span class="small">{{ `第 ${page.firstItem}~${page.firstItem + page.currentItems - 1} 条` }}</span>
            </div>
        </div>
        <div class="col align-middle"><hr /></div>
    </div>
    <scroll-list :items="usersData.users"
                 item-dynamic-dimensions :item-initial-dimensions="{ height: '20em' }"
                 :item-min-display-num="15" item-transition-name="user-item"
                 :item-outer-attrs="{ id: { type: 'eval', value: 'item.uid' } }"
                 :item-inner-attrs="{ class: { type: 'string', value: 'row' } }">
        <template v-slot="slotProps">
            <template v-for="user in [slotProps.item]">
                <div class="col-3">
                    <img class="lazyload d-block mx-auto badge badge-light" width="120px" height="120px"
                         :data-src="$data.$$getTiebaUserAvatarUrl(user.avatarUrl)" />
                </div>
                <div class="col">
                    <p>UID：{{ user.uid }}</p>
                    <p v-if="user.displayName !== null">覆盖ID：{{ user.displayName }}</p>
                    <p v-if="user.name !== null">用户名：{{ user.name }}</p>
                    <p>性别：{{ getUserGender(user.gender) }}</p>
                    <p v-if="user.fansNickname !== null">粉丝头衔：{{ user.fansNickname }}</p>
                </div>
                <div v-if="slotProps.itemIndex !== usersData.users.length - 1" class="w-100"><hr /></div>
            </template>
        </template>
    </scroll-list>
    <div v-if="! loadingNewUsers && ! isLastPageInPages" class="reply-list-next-page p-4">
        <div class="row align-items-center">
            <div class="col"><hr /></div>
            <div class="w-auto" v-for="page in [usersData.pages]">
                <button @click="queryNewPage(page.currentPage + 1)" class="btn btn-secondary" type="button"><span class="h4">下一页</span></button>
            </div>
            <div class="col"><hr /></div>
        </div>
    </div>
</template>

<script lang="ts">
import { computed, defineComponent, ref } from 'vue';
import { useRoute } from 'vue-router';

export default defineComponent({
    props: { // received from user list pages component
        usersData: { type: Object, required: true },
        loadingNewUsers: { type: Boolean, required: true },
        isLastPageInPages: { type: Boolean, required: true }
    },
    setup() {
        const route = useRoute();
        const displayingItemsID = ref([]);
        const getUserGender = (gender) => {
            const gendersList = {
                0: '未指定（显示为男）',
                1: '男 ♂',
                2: '女 ♀'
            };
            return gendersList[gender];
        };
        const queryNewPage = (pageNum, nextPageButtonDom) => {};
        const previousPageUrl = computed(() => { // computed function will caching attr to ensure each list component's url won't be updated after page param change
            // generate an new absolute url with previous page params which based on current route path
            const urlWithNewPage = route.fullPath.replace(`/page/${route.params.page}`, `/page/${route.params.page - 1}`);
            return `${$$baseUrl}${urlWithNewPage}`;
        });

        return { displayingItemsID, getUserGender, queryNewPage, previousPageUrl };
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
