<template>
    <div class="col-5">
        <div class="input-group">
            <select v-model="selectBy" class="selectUserBy form-select">
                <option value="">未选择</option>
                <option value="uid">UID</option>
                <option value="name">用户名</option>
                <option value="nameNULL">NULL用户名</option>
                <option value="displayName">覆盖名</option>
                <option value="displayNameNULL">NULL覆盖名</option>
            </select>
            <template v-if="selectBy === 'uid'">
                <select v-model="params.uidCompareBy" class="uidCompareBy form-select">
                    <option>&lt;</option>
                    <option>=</option>
                    <option>&gt;</option>
                </select>
                <input v-model="params.uid" type="number" placeholder="4000000000" aria-label="UID" class="form-control" required />
            </template>
            <template v-if="selectBy === 'name'">
                <input v-model="params.name" type="text" placeholder="n0099" aria-label="用户名" class="form-control" required />
                <div class="input-group-text">
                    <div class="form-check">
                        <input v-model="params.nameUseRegex" id="selectUserNameUseRegex" type="checkbox" class="form-check-input" />
                        <label class="form-check-label" for="selectUserNameUseRegex">正则</label>
                    </div>
                </div>
            </template>
            <template v-if="selectBy === 'displayName'">
                <input v-model="params.displayName" type="text" placeholder="神奇🍀" aria-label="覆盖名" class="form-control" required />
                <div class="input-group-text">
                    <div class="form-check">
                        <input v-model="params.displayNameUseRegex" id="selectUserDisplayNameUseRegex" type="checkbox" class="form-check-input" />
                        <label class="form-check-label" for="selectUserDisplayNameUseRegex">正则</label>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script lang="ts">
import type { BaiduUserID } from '@/api/index.d';
import type { ObjValues } from '@/shared';
import type { PropType } from 'vue';
import { defineComponent, onMounted, reactive, toRefs, watch } from 'vue';
import _ from 'lodash';

export const selectTiebaUserBy = ['', 'uid', 'name', 'nameNULL', 'displayName', 'displayNameNULL'] as const;
export type SelectTiebaUserBy = typeof selectTiebaUserBy[number];
export type SelectTiebaUserParams = Partial<{
    uid: BaiduUserID,
    uidCompareBy: '<' | '=' | '>',
    name: string | 'NULL',
    nameUseRegex: boolean,
    displayName: string | 'NULL',
    displayNameUseRegex: boolean
}>;
const selectTiebaUserParamsNames = ['uid', 'uidCompareBy', 'name', 'nameUseRegex', 'displayName', 'displayNameUseRegex'] as const;
type SelectTiebaUserParamsValues = ObjValues<SelectTiebaUserParams>;
// widen type Record<string, SelectTiebaUserParamsValues> for compatible with props.paramsNameMap
export interface SelectTiebaUserModel { selectBy: SelectTiebaUserBy, params: Record<string, SelectTiebaUserParamsValues> | SelectTiebaUserParams }

export default defineComponent({
    props: {
        modelValue: { type: Object as PropType<SelectTiebaUserModel>, required: true },
        paramsNameMap: Object as PropType<Record<keyof SelectTiebaUserParams, string>>
    },
    emits: {
        'update:modelValue': (p: SelectTiebaUserModel) =>
            _.isObject(p)
                && selectTiebaUserBy.includes(p.selectBy)
                && _.isObject(p.params) // todo: check p.params against props.paramsNameMap
    },
    setup(props, { emit }) {
        const state = reactive<{
            selectBy: SelectTiebaUserBy | 'displayNameNULL' | 'nameNULL',
            params: SelectTiebaUserParams
        }>({
            selectBy: '',
            params: {}
        });
        const emitModelChange = () => {
            if (props.paramsNameMap !== undefined) {
                state.params = _.mapKeys(state.params, (_v, oldParamName) =>
                    (props.paramsNameMap as Record<string, string>)[oldParamName]);
            }
            emit('update:modelValue', state);
        };

        watch(() => state.params, emitModelChange, { deep: true });
        watch(() => props.modelValue, () => {
            // emit with default params value when parent haven't passing modelValue
            if (_.isEmpty(props.modelValue)) emitModelChange();
            else ({ selectBy: state.selectBy, params: state.params } = props.modelValue);
            // filter out unnecessary and undefined params
            state.params = _.omitBy(_.pick(state.params, selectTiebaUserParamsNames), (i?: SelectTiebaUserParamsValues) => i === undefined);
            // reset to default selectBy if it's a invalid value
            if (!selectTiebaUserBy.includes(state.selectBy)) state.selectBy = '';
            if (state.selectBy === 'uid') state.params.uidCompareBy ??= '='; // set to default value if it's undefined
            if (state.params.name === 'NULL') state.selectBy = 'nameNULL';
            if (state.params.displayName === 'NULL') state.selectBy = 'displayNameNULL';
        }, { immediate: true });
        onMounted(() => {
            watch(() => state.selectBy, selectBy => { // defer listening to prevent watch triggered by assigning initial selectBy
                state.params = {}; // empty params to prevent old value remains after selectBy changed
                if (selectBy === 'uid') state.params.uidCompareBy = '='; // reset to default
                if (selectBy === 'nameNULL') state.params.name = 'NULL';
                if (selectBy === 'displayNameNULL') state.params.displayName = 'NULL';
                emitModelChange();
            });
        });

        return { ...toRefs(state) };
    }
});
</script>

<style scoped>
.selectUserBy {
    flex-grow: .3;
}
.uidCompareBy {
    flex-grow: .1;
}
</style>
