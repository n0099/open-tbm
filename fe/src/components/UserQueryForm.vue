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

<script lang="ts">
import type { BaiduUserID, TiebaUserGenderQP } from '@/api/index.d';
import { boolPropToStr, boolStrPropToBool, removeEnd } from '@/shared';
import type { SelectTiebaUserBy, SelectTiebaUserModel, SelectTiebaUserParams } from '@/components/SelectTiebaUser.vue';
import SelectTiebaUser, { selectTiebaUserBy } from '@/components/SelectTiebaUser.vue';

import type { PropType } from 'vue';
import { defineComponent, reactive, toRefs, watchEffect } from 'vue';
import type { LocationQueryValueRaw } from 'vue-router';
import { useRouter } from 'vue-router';
import _ from 'lodash';

type RouteQueryString = Omit<SelectTiebaUserParams, Exclude<SelectTiebaUserBy, ''>> & { gender?: TiebaUserGenderQP };
export default defineComponent({
    components: { SelectTiebaUser },
    props: {
        query: { type: Object as PropType<RouteQueryString>, required: true },
        params: {
            type: Object as PropType<{
                uid?: BaiduUserID,
                name?: string,
                displayName?: string
            }>,
            required: true
        },
        selectUserBy: { type: String as PropType<SelectTiebaUserBy>, required: true }
    },
    setup(props) {
        const router = useRouter();
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
            const params = boolPropToStr<LocationQueryValueRaw>(state.selectUser.params);
            const { selectBy } = state.selectUser;
            const routeName = removeEnd(selectBy, 'NULL');
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

        return { ...toRefs(state), submitQueryForm };
    }
});
</script>
