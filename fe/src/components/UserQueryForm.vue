<template>
    <form @submit.prevent="submitQueryForm()" class="row mt-3">
        <SelectTiebaUser v-model="selectUser" />
        <label class="col-2 col-form-label text-end" for="queryGender">性别</label>
        <div class="col-3">
            <select v-model="gender" id="queryGender" class="form-select">
                <option value="default">不限</option>
                <option value="0">未指定（显示为男）</option>
                <option value="1">男 ♂</option>
                <option value="2">女 ♀</option>
            </select>
        </div>
        <button type="submit" class="col-auto btn btn-primary">查询</button>
    </form>
</template>

<script lang="ts">
import { boolPropToStr, boolStrPropToBool } from '@/shared';
import type { SelectTiebaUserBy, SelectTiebaUserModel } from '@/components/SelectTiebaUser.vue';
import SelectTiebaUser, { selectTiebaUserBy } from '@/components/SelectTiebaUser.vue';

import type { PropType } from 'vue';
import { defineComponent, reactive, toRefs, watchEffect } from 'vue';
import type { LocationQueryValueRaw } from 'vue-router';
import { useRouter } from 'vue-router';
import _ from 'lodash';

export default defineComponent({
    components: { SelectTiebaUser },
    props: {
        query: { type: Object, required: true },
        params: { type: Object, required: true },
        selectUserBy: { type: String as PropType<SelectTiebaUserBy>, required: true }
    },
    setup(props) {
        const router = useRouter();
        const state = reactive<{
            gender: '0' | '1' | '2' | 'default',
            selectUser: SelectTiebaUserModel
        }>({
            gender: 'default',
            selectUser: { selectBy: 'name', params: {} }
        });

        const defaultParamsValue = {
            gender: 'default',
            uidCompareBy: '=',
            nameUseRegex: 'false',
            displayNameUseRegex: 'false'
        };
        const omitDefaultParamsValue = (params: Record<string, LocationQueryValueRaw>) => {
            _.each(defaultParamsValue, (value, param) => {
                if (params[param] === value || params[param] === undefined) Reflect.deleteProperty(params, param);
            });
            return params;
        };

        const submitQueryForm = () => {
            const params = boolPropToStr<LocationQueryValueRaw>(state.selectUser.params);
            router.push({
                name: _.isEmpty(params) ? 'user' : state.selectUser.selectBy,
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
