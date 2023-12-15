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
                <input v-model="params.uid" type="number" placeholder="4000000000"
                       aria-label="UID" class="form-control" required />
            </template>
            <template v-if="selectBy === 'name'">
                <input v-model="params.name" type="text" aria-label="用户名" class="form-control" required />
                <div class="input-group-text">
                    <div class="form-check">
                        <input v-model="params.nameUseRegex" id="selectUserNameUseRegex"
                               type="checkbox" class="form-check-input" />
                        <label class="form-check-label" for="selectUserNameUseRegex">正则</label>
                    </div>
                </div>
            </template>
            <template v-if="selectBy === 'displayName'">
                <input v-model="params.displayName" type="text" aria-label="覆盖名" class="form-control" required />
                <div class="input-group-text">
                    <div class="form-check">
                        <input v-model="params.displayNameUseRegex" id="selectUserDisplayNameUseRegex"
                               type="checkbox" class="form-check-input" />
                        <label class="form-check-label" for="selectUserDisplayNameUseRegex">正则</label>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script lang="ts">
import type { BaiduUserID } from '@/api/user';
import type { ObjValues } from '@/shared';

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
type SelectTiebaUserParamsValues = ObjValues<SelectTiebaUserParams>;
const selectTiebaUserParamsNames = [
    'uid', 'uidCompareBy', 'name', 'nameUseRegex', 'displayName', 'displayNameUseRegex'
] as const;
// widen type Record<string, SelectTiebaUserParamsValues> for compatible with props.paramsNameMap
export interface SelectTiebaUserModel {
    selectBy: SelectTiebaUserBy,
    params: Record<string, SelectTiebaUserParamsValues> | SelectTiebaUserParams
}
</script>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import _ from 'lodash';

const props = defineProps<{
    modelValue: SelectTiebaUserModel,
    paramsNameMap?: Record<keyof SelectTiebaUserParams, string>
}>();
const emit = defineEmits({
    'update:modelValue': (p: SelectTiebaUserModel) =>
        _.isObject(p)
        && selectTiebaUserBy.includes(p.selectBy)
        && _.isObject(p.params) // todo: check p.params against props.paramsNameMap
});
const selectBy = ref<SelectTiebaUserBy | 'displayNameNULL' | 'nameNULL'>('');
const params = ref<SelectTiebaUserParams>({});

const emitModelChange = () => {
    if (props.paramsNameMap !== undefined) {
        params.value = _.mapKeys(params, (_v, oldParamName) =>
            (props.paramsNameMap as Record<string, string>)[oldParamName]);
    }
    emit('update:modelValue', { selectBy: selectBy.value, params: params.value });
};

watch(() => params, emitModelChange, { deep: true });
watch(() => props.modelValue, () => {
    // emit with default params value when parent haven't passing modelValue
    if (_.isEmpty(props.modelValue))
        emitModelChange();
    else
        ({ selectBy: selectBy.value, params: params.value } = props.modelValue);
    // filter out unnecessary and undefined params
    params.value = _.omitBy(_.pick(params.value, selectTiebaUserParamsNames), _.isUndefined);
    // reset to default selectBy if it's a invalid value
    if (!selectTiebaUserBy.includes(selectBy.value))
        selectBy.value = '';
    if (selectBy.value === 'uid')
        params.value.uidCompareBy ??= '='; // set to default value if it's undefined
    if (params.value.name === 'NULL')
        selectBy.value = 'nameNULL';
    if (params.value.displayName === 'NULL')
        selectBy.value = 'displayNameNULL';
}, { immediate: true });
onMounted(() => {
    // defer listening to prevent watch triggered by assigning initial selectBy
    watch(() => selectBy.value, selectBy => {
        params.value = {}; // empty params to prevent old value remains after selectBy changed
        if (selectBy === 'uid')
            params.value.uidCompareBy = '='; // reset to default
        if (selectBy === 'nameNULL')
            params.value.name = 'NULL';
        if (selectBy === 'displayNameNULL')
            params.value.displayName = 'NULL';
        emitModelChange();
    });
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
