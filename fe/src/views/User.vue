<template>
    <UserQueryForm :query="$route.query" :params="params" :selectUserBy="selectUserBy" />
    <PlaceholderError404 v-show="showError404Placeholder" />
    <PlaceholderLoadingList v-show="showFirstLoadingPlaceholder" />
</template>

<script lang="ts">
import PlaceholderError404 from '@/components/PlaceholderError404.vue';
import PlaceholderLoadingList from '@/components/PlaceholderLoadingList.vue';
import type { SelectTiebaUserBy, SelectTiebaUserParams } from '@/components/SelectTiebaUser.vue';
import UserQueryForm from '@/components/UserQueryForm.vue';

import { defineComponent, reactive, toRefs, watchEffect } from 'vue';
import { useRoute } from 'vue-router';
import _ from 'lodash';

export default defineComponent({
    components: { UserQueryForm, PlaceholderError404, PlaceholderLoadingList },
    props: {
        page: String,
        uid: String,
        name: String,
        displayName: String
    },
    setup(props) {
        const route = useRoute();
        const state = reactive<{
            params: Pick<SelectTiebaUserParams, SelectTiebaUserBy>,
            selectUserBy: SelectTiebaUserBy,
            showFirstLoadingPlaceholder: boolean,
            showError404Placeholder: boolean
        }>({
            params: {},
            selectUserBy: 'name',
            showFirstLoadingPlaceholder: true, // show by initially
            showError404Placeholder: false
        });
        watchEffect(() => {
            state.selectUserBy = _.trimEnd(route.name?.toString(), '+p') as SelectTiebaUserBy;
            state.params = { ..._.omit(props, 'page'), uid: props.uid === undefined ? undefined : Number(props.uid) };
        });

        return { ...toRefs(state) };
    }
});
</script>
