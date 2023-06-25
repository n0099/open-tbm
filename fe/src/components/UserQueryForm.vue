<template>
    <form @submit.prevent="submitQueryForm" class="row">
        <SelectTiebaUser v-model="selectUser" />
        <label class="col-2 col-form-label text-end" for="queryGender">性别</label>
        <div class="col-3">
            <select v-model="gender" id="queryGender" class="form-select">
                <option value="default">不限</option>
                <option value="NULL">NULL</option>
                <option value="0">未指定（显示为男）</option>
                <option value="1">男 ♂</option>
                <option value="2">女 ♀</option>
            </select>
        </div>
        <button type="submit" class="col-auto btn btn-primary">查询</button>
    </form>
</template>

<script setup lang="ts">
import type { SelectTiebaUserBy, SelectTiebaUserModel, SelectTiebaUserParams } from './SelectTiebaUser.vue';
import SelectTiebaUser, { selectTiebaUserBy } from './SelectTiebaUser.vue';
import type { BaiduUserID, TiebaUserGenderQP } from '@/api/index.d';
import { boolPropToStr, boolStrPropToBool, removeEnd } from '@/shared';

import { reactive, watchEffect } from 'vue';
import type { LocationQueryValueRaw } from 'vue-router';
import { useRouter } from 'vue-router';
import _ from 'lodash';

type RouteQueryString = Omit<SelectTiebaUserParams, Exclude<SelectTiebaUserBy, ''>> & { gender?: TiebaUserGenderQP };

const router = useRouter();
const props = defineProps<{
    query: RouteQueryString,
    params: {
        uid?: BaiduUserID,
        name?: string,
        displayName?: string
    },
    selectUserBy: SelectTiebaUserBy
}>();
const state = reactive<{
    gender: TiebaUserGenderQP | 'default',
    selectUser: SelectTiebaUserModel
}>({
    gender: 'default',
    selectUser: { selectBy: '', params: {} }
});

const defaultParamsValue = {
    gender: 'default',
    uidCompareBy: '=',
    nameUseRegex: 'false',
    displayNameUseRegex: 'false'
} as const;
const omitDefaultParamsValue = (params: Record<string, LocationQueryValueRaw>) => {
    _.each(defaultParamsValue, (value, param) => {
        if (params[param] === value || params[param] === undefined) Reflect.deleteProperty(params, param);
    });
    return params;
};
const submitQueryForm = () => {
    /* eslint-disable @typescript-eslint/no-unsafe-argument */
    const params = boolPropToStr<LocationQueryValueRaw>(state.selectUser.params);
    const routeName = removeEnd(state.selectUser.selectBy, 'NULL');
    /* eslint-enable @typescript-eslint/no-unsafe-argument */
    router.push({
        name: `user${_.isEmpty(params) ? '' : `/${routeName}`}`,
        query: omitDefaultParamsValue({ ..._.omit(params, selectTiebaUserBy), gender: state.gender }),
        params: _.pick(params, selectTiebaUserBy)
    });
};

watchEffect(() => {
    state.gender = props.query.gender ?? defaultParamsValue.gender;
    state.selectUser = { selectBy: props.selectUserBy, params: { ...props.params, ...boolStrPropToBool(props.query) } };
});
</script>
