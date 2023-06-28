import type { ObjUnknown, ObjValues } from '@/shared';
import { boolStrToBool } from '@/shared';
import { onBeforeMount, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import _ from 'lodash';

export interface UnknownParam { name: string, value: unknown, subParam: ObjUnknown & { not?: boolean } }
export interface NamelessUnknownParam { value?: unknown, subParam?: ObjUnknown }
export type ParamPreprocessorOrWatcher = (p: UnknownParam) => void;
export default <
    UniqueParams extends Record<string, UnknownParam> = Record<string, UnknownParam>,
    Params extends Record<string, UnknownParam> = Record<string, UnknownParam>
>(
    deps: {
        paramsDefaultValue: Readonly<Partial<Record<string, NamelessUnknownParam>>>,
        paramsPreprocessor: Readonly<Partial<Record<string, ParamPreprocessorOrWatcher>>>,
        paramsWatcher: Readonly<Partial<Record<string, ParamPreprocessorOrWatcher>>>
    }
) => {
    type UniqueParam = ObjValues<UniqueParams>;
    type Param = ObjValues<Params>;
    const router = useRouter();
    const uniqueParams = ref<UniqueParams>({} as UniqueParams);
    const params = ref<Param[]>([]); // [{ name: '', value: '', subParam: { name: value } },...]
    const invalidParamsIndex = ref<number[]>([]);

    const fillParamWithDefaultValue = <T extends Param | UniqueParam>
    (param: Partial<UnknownParam> & { name: string }, resetToDefault = false): T => {
        // prevent defaultsDeep mutate origin paramsDefaultValue
        const defaultParam = _.cloneDeep(deps.paramsDefaultValue[param.name]);
        if (defaultParam === undefined) throw Error(`Param ${param.name} not found in paramsDefaultValue`);
        defaultParam.subParam ??= {};
        if (!Object.keys(uniqueParams.value).includes(param.name)) defaultParam.subParam.not = false; // add subParam.not on every param
        // cloneDeep to prevent defaultsDeep mutates origin object
        if (resetToDefault) return _.defaultsDeep(defaultParam, param);
        return _.defaultsDeep(_.cloneDeep(param), defaultParam);
    };
    const addParam = (name: string) => {
        params.value.push(fillParamWithDefaultValue({ name }));
    };
    const changeParam = (beforeParamIndex: number, afterParamName: string) => {
        _.pull(invalidParamsIndex.value, beforeParamIndex);
        params.value[beforeParamIndex] = fillParamWithDefaultValue({ name: afterParamName });
    };
    const deleteParam = (paramIndex: number) => {
        _.pull(invalidParamsIndex.value, paramIndex);
        // move forward index of all params, which after current
        invalidParamsIndex.value = invalidParamsIndex.value.map(invalidParamIndex =>
            (invalidParamIndex > paramIndex ? invalidParamIndex - 1 : invalidParamIndex));
        params.value.splice(paramIndex, 1);
    };
    const clearParamDefaultValue = <T extends UnknownParam>(param: UnknownParam): Partial<T | UnknownParam> | null => {
        const defaultParam = _.cloneDeep(deps.paramsDefaultValue[param.name]);
        if (defaultParam === undefined) throw Error(`Param ${param.name} not found in paramsDefaultValue`);
        // remove subParam.not: false, which previously added by fillParamWithDefaultValue()
        if (defaultParam.subParam !== undefined) defaultParam.subParam.not ??= false;
        const newParam: Partial<UnknownParam> = _.cloneDeep(param); // prevent mutating origin param
        // number will consider as empty in isEmpty(), to prevent this we use complex short circuit evaluate expression
        if (!(_.isNumber(newParam.value) || !_.isEmpty(newParam.value))
            || (_.isArray(newParam.value) && _.isArray(defaultParam.value)
                ? _.isEqual(_.sortBy(newParam.value), _.sortBy(defaultParam.value)) // sort array type param value for comparing
                : newParam.value === defaultParam.value)) delete newParam.value;

        _.each(defaultParam.subParam, (value, name) => {
            if (newParam.subParam === undefined) return;
            // undefined means this sub param must get deleted and merge into parent, as part of the parent param value
            // eslint-disable-next-line @typescript-eslint/no-dynamic-delete
            if (newParam.subParam[name] === value || value === undefined) delete newParam.subParam[name];
        });
        if (_.isEmpty(newParam.subParam)) delete newParam.subParam;

        return _.isEmpty(_.omit(newParam, 'name')) ? null : newParam; // return null for further filter()
    };
    const clearedParamsDefaultValue = (): Array<Partial<Param>> =>
        _.filter(params.value.map(clearParamDefaultValue)) as Array<Partial<Param>>; // filter() will remove falsy values like null
    const clearedUniqueParamsDefaultValue = (): Partial<UniqueParams> =>
        // mapValues() return object which remains keys, pickBy() like filter() for objects
        _.pickBy(_.mapValues(uniqueParams.value, clearParamDefaultValue)) as Partial<UniqueParams>;
    const flattenParams = (): ObjUnknown[] => {
        const flattenParam = (param: Partial<UnknownParam>) => {
            const flatted: ObjUnknown = {};
            flatted[param.name ?? 'undef'] = param.value;
            return { ...flatted, ...param.subParam };
        };
        return [
            ...Object.values(clearedUniqueParamsDefaultValue()).map(flattenParam),
            ...clearedParamsDefaultValue().map(flattenParam)
        ];
    };
    const escapeParamValue = (value: string | unknown, shouldUnescape = false) => {
        let ret = value;
        if (_.isString(value)) {
            _.each({ // we don't escape ',' since array type params is already known
                /* eslint-disable @typescript-eslint/naming-convention */
                '/': '%2F',
                ';': '%3B'
                /* eslint-enable @typescript-eslint/naming-convention */
            }, (encode, char) => {
                ret = value.replace(shouldUnescape ? encode : char, shouldUnescape ? char : encode);
            });
        }
        return ret;
    };
    const parseParamRoute = (routePath: string[]) => {
        _.chain(routePath)
            .map(paramWithSub => {
                const parsedParam: NamelessUnknownParam & { name: string } = { name: '', subParam: {} };
                paramWithSub.split(';').forEach((paramNameAndValue, paramIndex) => { // split multiple params
                    const paramPair: [string, unknown] = [
                        paramNameAndValue.substring(0, paramNameAndValue.indexOf(':')),
                        escapeParamValue(paramNameAndValue.substring(paramNameAndValue.indexOf(':') + 1), true)
                    ]; // split kv pair by first colon, using substr to prevent split array type param value
                    if (paramIndex === 0) { // main param
                        [parsedParam.name, parsedParam.value] = paramPair;
                    } else { // sub params
                        parsedParam.subParam = { ...parsedParam.subParam, [paramPair[0]]: paramPair[1] };
                    }
                });
                return parsedParam as UnknownParam;
            })
            .map(_.unary(fillParamWithDefaultValue))
            .each(param => {
                const preprocessor = deps.paramsPreprocessor[param.name];
                if (preprocessor !== undefined) {
                    preprocessor(param);
                    param.subParam.not = boolStrToBool(param.subParam.not);
                }
                const isUniqueParam = (p: UnknownParam): p is UniqueParam => p.name in uniqueParams.value;
                if (isUniqueParam(param)) { // is unique param
                    uniqueParams.value[param.name as keyof UniqueParams] = param;
                } else {
                    params.value.push(param as Param);
                }
            })
            .value();
    };
    const submitParamRoute = (filteredUniqueParams: Partial<UniqueParams>, filteredParams: Array<Partial<Param>>) => {
        const paramValue = (v: unknown) => escapeParamValue(_.isArray(v) ? v.join(',') : v);
        const subParamValue = (subParam?: UnknownParam['subParam']) =>
            _.map(subParam, (value, name) => `;${name}:${escapeParamValue(value)}`).join('');
        const flatParams = [...Object.values(filteredUniqueParams) as Param[], ...filteredParams];
        void router.push({
            path: `/p/${flatParams // format param to url, e.g. name:value;subParamName:subParamValue...
                .map(param => `${param.name}:${paramValue(param.value)}${subParamValue(param.subParam)}`)
                .join('/')}`
        });
    };

    watch([() => uniqueParams.value, () => params.value], newParamsArray => {
        const [newUniqueParams, newParams] = newParamsArray;
        _.chain([...Object.values(newUniqueParams), ...newParams])
            .filter(param => param.name in deps.paramsWatcher)
            .each(param => deps.paramsWatcher[param.name]?.(param))
            .value();
    }, { deep: true });

    onBeforeMount(() => {
        uniqueParams.value = _.mapValues(uniqueParams.value, _.unary(fillParamWithDefaultValue)) as UniqueParams;
        params.value = params.value.map(_.unary(fillParamWithDefaultValue)) as typeof params.value;
    });

    return {
        uniqueParams,
        params,
        invalidParamsIndex,
        addParam,
        changeParam,
        deleteParam,
        fillParamWithDefaultValue,
        clearParamDefaultValue,
        clearedParamsDefaultValue,
        clearedUniqueParamsDefaultValue,
        flattenParams,
        parseParamRoute,
        submitParamRoute
    };
};
