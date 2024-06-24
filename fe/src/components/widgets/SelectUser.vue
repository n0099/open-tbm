<template>
<div class="col-5">
    <div class="input-group">
        <select v-model="selectBy" class="select-user-by form-select">
            <option value="">未选择</option>
            <option value="uid">百度UID</option>
            <option value="name">用户名</option>
            <option value="nameNULL">NULL用户名</option>
            <option value="displayName">覆盖名</option>
            <option value="displayNameNULL">NULL覆盖名</option>
        </select>
        <template v-if="selectBy === 'uid'">
            <select v-model="params.uidCompareBy" class="uid-compare-by form-select">
                <option>&lt;</option>
                <option>=</option>
                <option>&gt;</option>
            </select>
            <input
                v-model="params.uid" type="number" placeholder="4000000000"
                aria-label="百度UID" class="form-control" required />
        </template>
        <template v-if="selectBy === 'name'">
            <input v-model="params.name" type="text" aria-label="用户名" class="form-control" required />
            <div class="input-group-text">
                <div class="form-check">
                    <input
                        v-model="params.nameUseRegex" type="checkbox"
                        class="form-check-input" id="selectUserNameUseRegex" />
                    <label class="form-check-label" for="selectUserNameUseRegex">正则</label>
                </div>
            </div>
        </template>
        <template v-if="selectBy === 'displayName'">
            <input v-model="params.displayName" type="text" aria-label="覆盖名" class="form-control" required />
            <div class="input-group-text">
                <div class="form-check">
                    <input
                        v-model="params.displayNameUseRegex" type="checkbox"
                        class="form-check-input" id="selectUserDisplayNameUseRegex" />
                    <label class="form-check-label" for="selectUserDisplayNameUseRegex">正则</label>
                </div>
            </div>
        </template>
    </div>
</div>
</template>

<script setup lang="ts">
import _ from 'lodash';

const props = defineProps<{
    modelValue: SelectUserModel,
    paramsNameMap?: Record<keyof SelectUserParams, string>
}>();
// eslint-disable-next-line vue/define-emits-declaration
const emit = defineEmits({
    'update:modelValue': (p: SelectUserModel) =>
        _.isObject(p)
        && selectUserBy.includes(p.selectBy)
        && _.isObject(p.params) // todo: check p.params against props.paramsNameMap
});
const selectBy = ref<SelectUserBy | 'displayNameNULL' | 'nameNULL'>('');
const params = ref<SelectUserParams>({});

const emitModelChange = () => {
    if (props.paramsNameMap !== undefined) {
        params.value = _.mapKeys(params, (_v, oldParamName) =>
            (props.paramsNameMap as Record<string, string>)[oldParamName]);
    }
    emit('update:modelValue', { selectBy: selectBy.value, params: params.value });
};

watch(params, emitModelChange, { deep: true });
watch(() => props.modelValue, () => {
    // emit with default params value when parent haven't passing modelValue
    if (_.isEmpty(props.modelValue))
        emitModelChange();
    else
        ({ selectBy: selectBy.value, params: params.value } = props.modelValue);

    // filter out unnecessary and undefined params
    // eslint-disable-next-line @typescript-eslint/unbound-method
    params.value = _.omitBy(_.pick(params.value, selectUserParamsName), _.isUndefined);

    // reset to default selectBy if it's a invalid value
    if (!selectUserBy.includes(selectBy.value))
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
    watchEffect(() => {
        params.value = {}; // empty params to prevent old value remains after selectBy changed
        if (selectBy.value === 'uid')
            params.value.uidCompareBy = '='; // reset to default
        if (selectBy.value === 'nameNULL')
            params.value.name = 'NULL';
        if (selectBy.value === 'displayNameNULL')
            params.value.displayName = 'NULL';
        emitModelChange();
    });
});
</script>

<style scoped>
.select-user-by {
    flex-grow: .3;
}
.uid-compare-by {
    flex-grow: .1;
}
</style>
