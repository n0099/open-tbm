import type { ObjUnknown, ObjValues } from '@/utils';
import type { RouteObjectRaw } from '@/stores/triggerRouteUpdate';
import type { Ref } from 'vue';
import _ from 'lodash';

export interface UnknownParam { name: string, value: unknown, subParam: ObjUnknown & { not?: boolean } }
export interface NamelessUnknownParam { value?: unknown, subParam?: ObjUnknown }
export type ParamPreprocessorOrWatcher = (p: UnknownParam) => void;
const useQueryForm = <
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

    // [{ name: '', value: '', subParam: { name: value } },...]
    const uniqueParams = ref<UniqueParams>({} as UniqueParams) as Ref<UniqueParams>;
    const params = ref<Param[]>([]) as Ref<Param[]>;
    const invalidParamsIndex = ref<number[]>([]);

    const fillParamDefaultValue = <T extends Param | UniqueParam>
    (param: Partial<UnknownParam> & { name: string }, resetToDefault = false): T => {
        // prevent defaultsDeep mutate origin paramsDefaultValue
        const defaultParam = structuredClone(deps.paramsDefaultValue[param.name]);
        if (defaultParam === undefined)
            throw new Error(`Param ${param.name} not found in paramsDefaultValue`);
        defaultParam.subParam ??= {};
        if (!Object.keys(uniqueParams.value).includes(param.name))
            defaultParam.subParam.not = false; // add subParam.not on every param
        // cloneDeep to prevent defaultsDeep mutates origin object
        if (resetToDefault)
            return _.defaultsDeep(defaultParam, param) as T;

        return _.defaultsDeep(refDeepClone(param), defaultParam) as T;
    };
    const addParam = (name: string) => {
        params.value.push(fillParamDefaultValue({ name }));
    };
    const changeParam = (beforeParamIndex: number, afterParamName: string) => {
        _.pull(invalidParamsIndex.value, beforeParamIndex);
        params.value[beforeParamIndex] = fillParamDefaultValue({ name: afterParamName });
    };
    const deleteParam = (paramIndex: number) => {
        _.pull(invalidParamsIndex.value, paramIndex);

        // move forward index of all params, which after current
        invalidParamsIndex.value = invalidParamsIndex.value.map(invalidParamIndex =>
            (invalidParamIndex > paramIndex ? invalidParamIndex - 1 : invalidParamIndex));
        params.value.splice(paramIndex, 1);
    };
    const clearParamDefaultValue = <T extends UnknownParam>(param: UnknownParam): Partial<T | UnknownParam> | null => {
        const defaultParam = structuredClone(deps.paramsDefaultValue[param.name]);
        if (defaultParam === undefined)
            throw new Error(`Param ${param.name} not found in paramsDefaultValue`);

        /** remove subParam.not: false, which previously added by {@link fillParamDefaultValue()} */
        if (defaultParam.subParam !== undefined)
            defaultParam.subParam.not ??= false;
        const newParam: Partial<UnknownParam> = refDeepClone(param); // prevent mutating origin param
        /** number will consider as empty in {@link _.isEmpty()} */
        // to prevent this we use complex short circuit evaluate expression
        if (!(_.isNumber(newParam.value) || !_.isEmpty(newParam.value))
            || (_.isArray(newParam.value) && _.isArray(defaultParam.value)

                // sort array type param value for comparing
                ? _.isEqual(_.sortBy(newParam.value), _.sortBy(defaultParam.value))
                : newParam.value === defaultParam.value))
            delete newParam.value;

        _.each(defaultParam.subParam, (value, name) => {
            if (newParam.subParam === undefined)
                return;

            // undefined means this sub param must get deleted and merge into parent, as part of the parent param value
            if (newParam.subParam[name] === value || value === undefined)
                Reflect.deleteProperty(newParam.subParam, name);
        });
        if (_.isEmpty(newParam.subParam))
            delete newParam.subParam;

        /** return null for further {@link _.filter()} */
        return _.isEmpty(_.omit(newParam, 'name')) ? null : newParam;
    };
    const clearedParamsDefaultValue = (): Array<Partial<Param>> =>

        /** {@link _.filter()} will remove falsy values like null */
        _.filter(params.value.map(clearParamDefaultValue)) as Array<Partial<Param>>;
    const clearedUniqueParamsDefaultValue = (): Partial<UniqueParams> =>

        /** {@link _.mapValues()} return object which remains keys, {@link _.pickBy()} like {@link _.filter()} for objects */
        _.pickBy(_.mapValues(uniqueParams.value, clearParamDefaultValue)) as Partial<UniqueParams>;
    const removeUndefinedFromPartialObjectValues = <T extends Partial<T>, R>(object: Partial<T>) =>
        Object.values(object).filter(i => i !== undefined) as R[];
    const flattenParams = (): ObjUnknown[] => {
        const flattenParam = (param: Partial<UnknownParam>) => {
            const flatted: ObjUnknown = {};
            flatted[param.name ?? 'undef'] = param.value;

            return { ...flatted, ...param.subParam };
        };
        const clearedUniqueParamsDefaultValueWithoutUndefined =
            removeUndefinedFromPartialObjectValues<UniqueParams, UnknownParam>(clearedUniqueParamsDefaultValue());

        return [
            ...clearedUniqueParamsDefaultValueWithoutUndefined.map(flattenParam),
            ...clearedParamsDefaultValue().map(flattenParam)
        ];
    };
    const escapeParamValueMapping = { // we don't escape ',' since array type params is already known
        /* eslint-disable @typescript-eslint/naming-convention */
        '/': '%2F',
        ';': '%3B'
        /* eslint-enable @typescript-eslint/naming-convention */
    };
    const replaceEach = (
        value: string,
        mapping: Record<string, string>,
        replacer: (acc: string, to: string, from: string) => string
    ): string => _.reduce(mapping, replacer, value);
    const unescapeParamValue = (value: string) => replaceEach(value,
        _.invert(escapeParamValueMapping),
        (acc, raw, escaped) => acc.replace(raw, escaped));
    const escapeParamValue = (value: string) => replaceEach(value,
        escapeParamValueMapping,
        (acc, escaped, raw) => acc.replace(escaped, raw));
    const parseParamRoute = (routePath: string[]) => {
        _.chain(routePath)
            .map(paramWithSub => {
                const parsedParam: NamelessUnknownParam & { name: string } = { name: '', subParam: {} };
                paramWithSub.split(';').forEach((paramNameAndValue, paramIndex) => { // split multiple params
                    const paramPair: [string, unknown] = [
                        paramNameAndValue.slice(0, Math.max(0, paramNameAndValue.indexOf(':'))),
                        unescapeParamValue(paramNameAndValue.slice(Math.max(0, paramNameAndValue.indexOf(':') + 1)))
                    ]; // split kv pair by first colon, using substr to prevent split array type param value
                    if (paramIndex === 0) { // main param
                        [parsedParam.name, parsedParam.value] = paramPair;
                    } else { // sub params
                        parsedParam.subParam = { ...parsedParam.subParam, [paramPair[0]]: paramPair[1] };
                    }
                });

                return parsedParam as UnknownParam;
            })
            .map(_.unary(fillParamDefaultValue))
            .each(param => {
                const preprocessor = deps.paramsPreprocessor[param.name];
                if (preprocessor !== undefined) {
                    preprocessor(param);
                    param.subParam.not = boolStrToBool(param.subParam.not);
                }
                const isUniqueParam = (p: UnknownParam): p is UniqueParam => p.name in uniqueParams.value;
                if (isUniqueParam(param))
                    uniqueParams.value[param.name as keyof UniqueParams] = param;
                else
                    params.value.push(param);
            })
            .value();
    };
    const generateParamRoute = (
        filteredUniqueParams: Partial<UniqueParams>,
        filteredParams: Array<Partial<Param>>
    ): RouteObjectRaw => {
        const tryEscapeParamValue = <T>(v: T): string | T => {
            if (_.isString(v))
                return escapeParamValue(v);
            if (_.isArray(v))
                return escapeParamValue(v.join(','));

            return v;
        };
        const tryEncodeSubParamValue = (subParam?: UnknownParam['subParam']) =>
            _.map(subParam, (value, name) =>
                // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
                `;${name}:${_.isString(value) ? escapeParamValue(value) : value}`).join('');
        const flatParams = [
            ...removeUndefinedFromPartialObjectValues<UniqueParams, UniqueParam>(filteredUniqueParams),
            ...filteredParams
        ];

        return {
            path: `/posts/${flatParams // format param to url, e.g. name:value;subParamName:subParamValue...
                .map(param =>
                    // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
                    `${param.name}:${tryEscapeParamValue(param.value)}${tryEncodeSubParamValue(param.subParam)}`)
                .join('/')}`
        };
    };

    watch([uniqueParams, params], newParamsArray => {
        const [newUniqueParams, newParams] = newParamsArray;
        _.chain([...Object.values(newUniqueParams), ...newParams])
            .filter(param => param.name in deps.paramsWatcher)
            .each(param => deps.paramsWatcher[param.name]?.(param))
            .value();
    }, { deep: true });

    onBeforeMount(() => {
        uniqueParams.value = _.mapValues(uniqueParams.value, _.unary(fillParamDefaultValue)) as UniqueParams;
        params.value = params.value.map(_.unary(fillParamDefaultValue)) as typeof params.value;
    });

    return {
        uniqueParams,
        params,
        invalidParamsIndex,
        addParam,
        changeParam,
        deleteParam,
        fillParamDefaultValue,
        clearParamDefaultValue,
        clearedParamsDefaultValue,
        clearedUniqueParamsDefaultValue,
        flattenParams,
        parseParamRoute,
        generateParamRoute
    };
};
export default useQueryForm;
