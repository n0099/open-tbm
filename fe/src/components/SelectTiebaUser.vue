<template>
    <div class="col-5">
        <div class="input-group">
            <select v-model="selectBy" class="selectUserBy form-select">
                <option value="uid">UID</option>
                <option value="name">用户名</option>
                <option value="displayName">覆盖名</option>
            </select>
            <template v-if="selectBy === 'uid'">
                <select v-model="params.uidCompareBy" class="uidCompareBy form-select">
                    <option>&lt;</option>
                    <option>=</option>
                    <option>&gt;</option>
                </select>
                <input v-model="params.uid" type="number" placeholder="4000000000" aria-label="UID" class="form-control" required>
            </template>
            <template v-if="selectBy === 'name'">
                <input v-model="params.name" type="text" placeholder="n0099" aria-label="用户名" class="form-control" required>
                <div class="input-group-text">
                    <div class="form-check">
                        <input v-model="params.nameUseRegex" id="selectUserNameUseRegex" type="checkbox" class="form-check-input">
                        <label class="form-check-label" for="selectUserNameUseRegex">正则</label>
                    </div>
                </div>
            </template>
            <template v-if="selectBy === 'displayName'">
                <input v-model="params.displayName" type="text" placeholder="神奇🍀" aria-label="覆盖名" class="form-control" required>
                <div class="input-group-text">
                    <div class="form-check">
                        <input v-model="params.displayNameUseRegex" id="selectUserDisplayNameUseRegex" type="checkbox" class="form-check-input">
                        <label class="form-check-label" for="selectUserDisplayNameUseRegex">正则</label>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent, reactive, toRefs, watch, watchEffect } from 'vue';
import _ from 'lodash';

export const selectTiebaUserBy = ['uid', 'name', 'displayName'] as const;
export type SelectTiebaUserBy = typeof selectTiebaUserBy[number];
export type SelectTiebaUserParams = Partial<{
    uid: number,
    uidCompareBy: '<' | '=' | '>',
    name: string,
    nameUseRegex: boolean,
    displayName: string,
    displayNameUseRegex: boolean
}>;
const selectTiebaUserParamsNames = ['uid', 'uidCompareBy', 'name', 'nameUseRegex', 'displayName', 'displayNameUseRegex'] as const;
type SelectTiebaUserParamsValues = SelectTiebaUserParams[keyof SelectTiebaUserParams];
// widen type Record<string, SelectTiebaUserParamsValues> for compatible with props.paramsNameMap
export interface SelectTiebaUserModel { selectBy: SelectTiebaUserBy, params: Record<string, SelectTiebaUserParamsValues> | SelectTiebaUserParams }

export default defineComponent({
    props: {
        modelValue: Object as PropType<SelectTiebaUserModel>,
        paramsNameMap: Object as PropType<SelectTiebaUserParams>
    },
    setup(props, { emit }) {
        const state = reactive<{
            selectBy: SelectTiebaUserBy,
            params: SelectTiebaUserParams
        }>({
            selectBy: 'name',
            params: {}
        });

        const emitChanged = () => {
            // deep remove proxy added by Vue.reactive() on state, can't use _.cloneDeep() here since it won't remove proxy
            // const eventValue: typeof state = JSON.parse(JSON.stringify(state));
            const eventValue = state;
            if (props.paramsNameMap !== undefined) {
                eventValue.params = _.mapKeys(eventValue.params, (_v, oldParamName) =>
                    (props.paramsNameMap as Record<string, string>)[oldParamName]);
            }
            emit('update:modelValue', eventValue);
        };

        watch(() => state.params, emitChanged, { deep: true });
        watchEffect(() => {
            // emit with default params value when parent haven't passing modelValue
            if (_.isEmpty(props.modelValue) || props.modelValue === undefined) emitChanged();
            else ({ selectBy: state.selectBy, params: state.params } = props.modelValue);
            // filter out unnecessary and undefined params
            state.params = _.omitBy(_.pick(state.params, selectTiebaUserParamsNames), (i?: SelectTiebaUserParamsValues) => i === undefined);
            // reset to default selectBy if it's a invalid value
            if (!_.includes(selectTiebaUserBy, state.selectBy)) state.selectBy = 'name';
            if (state.selectBy === 'uid') state.params.uidCompareBy ??= '='; // set to default value if it's undefined
            watch(() => state.selectBy, () => { // defer listening to prevent watch triggered by assigning initial selectBy
                state.params = {}; // empty params to prevent old value remains after selectBy changed
                if (state.selectBy === 'uid') state.params.uidCompareBy = '='; // reset to default
                emitChanged();
            });
        });

        return { ...toRefs(state) };
    }
});
</script>

<style scoped>
.selectUserBy {
    flex-grow: 0.3;
}
.uidCompareBy {
    flex-grow: 0.1;
}
</style>
