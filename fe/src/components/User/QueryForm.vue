<template>
    <form @submit.prevent="_ => submitQueryForm()" class="row">
        <SelectUser v-model="selectUser" />
        <label class="col-2 col-form-label text-end" for="queryGender">性别</label>
        <div class="col-3">
            <select v-model="gender" class="form-select" id="queryGender">
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
import type { SelectUserBy, SelectUserModel, SelectUserParams } from '../widgets/selectUser';
import SelectUser from '../widgets/SelectUser.vue';
import { selectUserBy as selectUserByAll } from '../widgets/selectUser';
import type { BaiduUserID, UserGenderQueryParam } from '@/api/user';
import { boolPropToStr, boolStrPropToBool, removeEnd } from '@/shared';

import { ref, watchEffect } from 'vue';
import type { LocationQueryValueRaw } from 'vue-router';
import { useRouter } from 'vue-router';
import * as _ from 'lodash-es';

type RouteQueryString = Omit<SelectUserParams, Exclude<SelectUserBy, ''>>
    & { gender?: UserGenderQueryParam };

const props = defineProps<{
    query: RouteQueryString,
    params: {
        uid?: BaiduUserID,
        name?: string,
        displayName?: string
    },
    selectUserBy: SelectUserBy
}>();
const router = useRouter();
const gender = ref<UserGenderQueryParam | 'default'>('default');
const selectUser = ref<SelectUserModel>({ selectBy: '', params: {} });

const paramsDefaultValue = {
    gender: 'default',
    uidCompareBy: '=',
    nameUseRegex: 'false',
    displayNameUseRegex: 'false'
} as const;
const omitDefaultParamsValue = (params: Record<string, LocationQueryValueRaw>) => {
    _.each(paramsDefaultValue, (value, param) => {
        if (params[param] === value || params[param] === undefined)
            Reflect.deleteProperty(params, param);
    });

    return params;
};
const submitQueryForm = async () => {
    const params = boolPropToStr<LocationQueryValueRaw>(selectUser.value.params);
    const routeName = removeEnd(selectUser.value.selectBy, 'NULL');

    return router.push({
        name: `user${_.isEmpty(params) ? '' : `/${routeName}`}`,
        query: omitDefaultParamsValue({ ..._.omit(params, selectUserByAll), gender: gender.value }),
        params: _.pick(params, selectUserByAll)
    });
};

watchEffect(() => {
    gender.value = props.query.gender ?? paramsDefaultValue.gender;
    selectUser.value = {
        selectBy: props.selectUserBy,
        params: { ...props.params, ...boolStrPropToBool(props.query) }
    };
});
</script>
