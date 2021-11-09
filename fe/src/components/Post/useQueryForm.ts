import { onBeforeMount, onMounted, reactive, watch } from 'vue';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { useRoute, useRouter } from 'vue-router';
import _ from 'lodash';

export interface Param { name: string, value?: unknown, subParam?: Record<string, unknown> }
export type ParamOmitName = Omit<Param, 'name'>;
export type ParamPreprocessorOrWatcher = (p: Param) => void;
export default (
    emit: (event: string, ...args: unknown[]) => void,
    { parseRoute, checkParams, submitRoute }: {
        parseRoute?: (route: RouteLocationNormalizedLoaded) => void,
        checkParams?: () => boolean,
        submitRoute?: () => void
    }
) => {
    const route = useRoute();
    const router = useRouter();
    const state = reactive<{
        isLoading: boolean,
        uniqueParams: Record<string, Param>,
        params: Param[],
        invalidParamsIndex: number[],
        paramsDefaultValue: Partial<Record<string, ParamOmitName>>,
        paramsPreprocessor: Partial<Record<string, ParamPreprocessorOrWatcher>>,
        paramsWatcher: Partial<Record<string, ParamPreprocessorOrWatcher>>
    }>({
        isLoading: false,
        uniqueParams: {},
        params: [], // [{ name: '', value: '', subParam: { name: value } },...]
        invalidParamsIndex: [],
        paramsDefaultValue: {}, // { name: *, subParam: { not: false } }
        paramsPreprocessor: {},
        paramsWatcher: {}
    });

    const paramRowLastDomClass = (paramIndex: number, params: typeof state.params) =>
        (params.length === 1
            ? {} // if there only have one row, class remains unchanged
            : {
                'param-control-first-row': paramIndex === 0,
                'param-control-middle-row': !(paramIndex === 0 || paramIndex === params.length - 1),
                'param-control-last-row': paramIndex === params.length - 1
            });
    const fillParamWithDefaultValue = (param: Param, resetToDefault = false): Param => {
        const defaultParam = _.cloneDeep(state.paramsDefaultValue[param.name]);
        if (defaultParam === undefined) return param;
        defaultParam.subParam ??= {};
        defaultParam.subParam.not = false; // add default not subParam on every param
        // cloneDeep to prevent defaultsDeep mutates origin object
        if (resetToDefault) return _.defaultsDeep(defaultParam, param);
        return _.defaultsDeep(_.cloneDeep(param), defaultParam);
    };
    const addParam = (selectDom: HTMLSelectElement) => {
        state.params.push(fillParamWithDefaultValue({ name: selectDom.value }));
        selectDom.value = 'add'; // reset to add option
    };
    const changeParam = (beforeParamIndex: number, afterParamName: string) => {
        _.pull(state.invalidParamsIndex, beforeParamIndex);
        state.params[beforeParamIndex] = fillParamWithDefaultValue({ name: afterParamName });
    };
    const deleteParam = (paramIndex: number) => {
        _.pull(state.invalidParamsIndex, paramIndex);
        // move forward index of all params, which after current
        state.invalidParamsIndex = _.map(state.invalidParamsIndex, invalidParamIndex =>
            (invalidParamIndex > paramIndex ? invalidParamIndex - 1 : invalidParamIndex));
        delete state.params[paramIndex];
    };
    const clearParamDefaultValue = (param: Param) => {
        const newParam = _.cloneDeep(param); // prevent mutating origin param
        const defaultParam = state.paramsDefaultValue[newParam.name];
        if (defaultParam === undefined) {
            if (newParam.subParam === undefined) delete newParam.subParam;
            return newParam;
        }
        // number will consider as empty in isEmpty(), to prevent this we use complex short circuit evaluate expression
        if (!(_.isNumber(newParam.value) || !_.isEmpty(newParam.value))
            || (_.isArray(newParam.value) && _.isArray(defaultParam.value)
                ? _.isEqual(_.sortBy(newParam.value), _.sortBy(defaultParam.value)) // sort array type param value for comparing
                : newParam.value === defaultParam.value)) delete newParam.value;

        _.each(defaultParam.subParam, (value, _name) => {
            if (newParam.subParam === undefined) return;
            // undefined means this sub param must get deleted, as part of the parent param value
            if (newParam.subParam[_name] === value || value === undefined) delete newParam.subParam[_name];
        });
        if (_.isEmpty(newParam.subParam)) delete newParam.subParam;

        return _.isEqual(_.keys(newParam), ['name']) ? null : newParam; // return null for further filter()
    };
    const clearedParamsDefaultValue = (): Param[] =>
        _.filter(_.map(state.params, clearParamDefaultValue)) as Param[]; // filter() will remove falsy values like null
    const clearedUniqueParamsDefaultValue = (...omitParams: string[]): typeof state.uniqueParams =>
        // mapValues() return object which remains keys, pickBy() like filter() for objects
        _.pickBy(_.mapValues(_.omit(state.uniqueParams, omitParams), clearParamDefaultValue));
    const flattenParams = (): Array<Record<string, unknown>> => {
        const flattenParam = (param: Param) => {
            const flatted: Record<string, unknown> = {};
            flatted[param.name] = param.value;
            return { ...flatted, ...param.subParam };
        };
        return [
            ..._.map(Object.values(clearedUniqueParamsDefaultValue()), flattenParam),
            ..._.map<Param, Record<string, unknown>>(clearedParamsDefaultValue(), flattenParam)
        ];
    };
    const escapeParamValue = (value: string | unknown, shouldUnescape = false) => {
        let ret = value;
        if (_.isString(value)) {
            _.each({ // we don't escape ',' since array type params is already known
                '/': '%2F',
                ';': '%3B'
            }, (encode, char) => {
                ret = value.replace(shouldUnescape ? encode : char, shouldUnescape ? char : encode);
            });
        }
        return ret;
    };
    const parseParamRoute = (routePath: string) => {
        _.chain(routePath)
            .trim('/')
            .split('/')
            .filter() // filter() will remove falsy values like ''
            .map(paramWithSub => {
                const parsedParam: Param = { name: '' };
                _.each(paramWithSub.split(';'), (params, paramIndex) => { // split multiple params
                    const paramPair: [string, unknown] = [
                        params.substr(0, params.indexOf(':')),
                        escapeParamValue(params.substr(params.indexOf(':') + 1), true)
                    ]; // split kv pair by first colon, using substr to prevent split array type param value
                    if (paramIndex === 0) { // main param
                        [parsedParam.name, parsedParam.value] = paramPair;
                    } else { // sub params
                        parsedParam.subParam = { ...parsedParam.subParam, [paramPair[0]]: paramPair[1] };
                    }
                });
                return parsedParam;
            })
            .map(_.unary(fillParamWithDefaultValue))
            .each(param => {
                const preprocessor = state.paramsPreprocessor[param.name];
                if (preprocessor !== undefined) {
                    preprocessor(param);
                    param.subParam.not = param.subParam.not === 'true'; // literal string convert to bool
                }
                if (param.name in state.uniqueParams) { // is unique param
                    state.uniqueParams[param.name] = param;
                } else {
                    state.params.push(param);
                }
            })
            .value();
    };
    const submitParamRoute = (filteredUniqueParams: typeof state.uniqueParams, filteredParams: typeof state.params) => {
        const paramValue = (v: unknown) => escapeParamValue(_.isArray(v) ? v.join(',') : v);
        const subParamValue = (subParam: Param['subParam']) =>
            _.map(subParam, (value, _name) => `;${_name}:${escapeParamValue(value)}`).join('');
        void router.push({
            path: `/${_.chain([...Object.values(filteredUniqueParams), ...filteredParams])
                // format param to route path, e.g. name:value;subParamName:subParamValue...
                .map(param => `${param.name}:${paramValue(param.value)}${subParamValue(param.subParam)}`)
                .join('/')
                .value()}`
        });
    };
    const submit = () => {
        if (checkParams === undefined || submitRoute === undefined) throw Error();
        if (checkParams()) { // check here to stop route submit
            state.isLoading = true;
            submitRoute();
            // force emit event to refresh new query since route update event won't emit when isLoading is true
            emit('query', { queryParams: flattenParams(), shouldReplacePage: true });
        }
    };

    watch([() => state.uniqueParams, () => state.params], newParamsArray => {
        const [uniqueParams, params] = newParamsArray;
        _.chain([...Object.values(uniqueParams), ...params])
            .filter(param => param.name in state.paramsWatcher)
            .each(param => state.paramsWatcher[param.name]?.(param))
            .value();
    }, { deep: true });
    watch(route, (to, from) => {
        if (parseRoute === undefined || checkParams === undefined) throw Error();
        if (to.path === from.path) return; // ignore when only hash has changed
        if (!state.isLoading) { // isLoading will be false when route change is not emit by <query-form>.submit()
            // these query logic is for route changes which is not trigger by <query-form>.submit(), such as user emitted history.back() or go()
            const isOnlyPageChanged = _.isEqual(_.omit(to.params, 'page'), _.omit(from.params, 'page'));
            parseRoute(to);
            if (isOnlyPageChanged || checkParams()) { // skip checkParams() when there's only page changed
                state.isLoading = true;
                emit('query', { queryParams: flattenParams(), shouldReplacePage: !isOnlyPageChanged });
            }
        }
    });

    onBeforeMount(() => {
        state.uniqueParams = _.mapValues(state.uniqueParams, _.unary(fillParamWithDefaultValue));
        state.params = _.map(state.params, _.unary(fillParamWithDefaultValue));
    });
    onMounted(() => {
        if (parseRoute === undefined || checkParams === undefined) throw Error();
        parseRoute(route); // first time parse
        if (checkParams()) { // query manually since route update event won't trigger while first load
            state.isLoading = true;
            emit('query', { queryParams: flattenParams(), shouldReplacePage: true });
        }
    });

    return {
        state,
        paramRowLastDomClass,
        escapeParamValue,
        addParam,
        changeParam,
        deleteParam,
        fillParamWithDefaultValue,
        clearParamDefaultValue,
        clearedParamsDefaultValue,
        clearedUniqueParamsDefaultValue,
        parseParamRoute,
        submitParamRoute,
        submit
    };
};
