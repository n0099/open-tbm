import type { UnwrapRef } from 'vue';
import type { RouteLocationNormalized } from 'vue-router';
import _ from 'lodash';

export type Param = ObjValues<KnownParams>;
export const isPostIDParam = (param: Param): param is AddNameToParam<PostIDStr, NamelessParamNumeric> =>
    (postID as Writable<typeof postID> as string[]).includes(param.name);
export const isTextParam = (param: Param): param is KnownTextParams =>
    (paramNamesKeyByType.text as Writable<typeof paramNamesKeyByType.text> as string[]).includes(param.name);
export const isDateTimeParam = (param: Param): param is KnownDateTimeParams =>
    (paramNamesKeyByType.dateTime as Writable<typeof paramNamesKeyByType.dateTime> as string[]).includes(param.name);

export type QueryFormDeps = ReturnType<typeof getQueryFormDeps>;
export const getQueryFormDeps = () => {
    const router = useRouter();
    const isOrderByInvalid = ref(false);
    const queryFormWithUniqueParams = useQueryFormWithUniqueParams();
    const {
        uniqueParams,
        params,
        invalidParamsIndex,
        fillParamDefaultValue,
        clearParamDefaultValue,
        clearedParamsDefaultValue,
        clearedUniqueParamsDefaultValue,
        flattenParams,
        parseParamRoute,
        generateParamRoute
    } = queryFormWithUniqueParams;

    const isFidParamExists = (paramsToFind: Array<Partial<ArrayElement<UnwrapRef<typeof params>>>>) =>
        paramsToFind.some(param => param.name === 'fid');
    const getFidParams = (paramsToFind: Array<Partial<ArrayElement<UnwrapRef<typeof params>>>>) =>
        paramsToFind.filter(param => param.name === 'fid') as Array<KnownParams['fid']> | undefined;
    const currentQueryType = computed(() => {
        const clearedParams = clearedParamsDefaultValue();
        if (_.isEmpty(clearedUniqueParamsDefaultValue())) {
            if (_.isEmpty(clearedParams))
                return 'empty';
            if (isFidParamExists(clearedParams))
                return 'fid';
        }

        // is there no other params except post id params
        if (_.isEmpty(_.reject(clearedParams, isPostIDParam))

            // is all post ID params doesn't own any sub param
            && _.chain(clearedParams).map('subParam').filter().isEmpty().value())
            return 'postID';

        return 'search';
    });
    const generateRoute = (): RouteObjectRaw => { // decide which route to go
        const clearedParams = clearedParamsDefaultValue();
        const clearedUniqueParams = clearedUniqueParamsDefaultValue();
        if (_.isEmpty(clearedUniqueParams)) { // check whether query by post id or not
            for (const postIDName of _.reverse(postID)) {
                const postIDParam = clearedParams.filter(p => p.name === postIDName);
                if (_.isEmpty(clearedParams.filter(p => p.name !== postIDName)) // is there no other params
                    && postIDParam.length === 1 // is there only one post id param
                    && postIDParam[0]?.subParam === undefined) { // is range subParam not set
                    // exit early to prevent pushing other route
                    return {
                        name: `posts/${postIDName}`,
                        params: { [postIDName]: postIDParam[0].value?.toString() }
                    };
                }
            }
        }
        const fidParams = getFidParams(clearedParams);
        if (isFidParamExists(clearedParams)
            && _.isEmpty(clearedParams.filter(p => p.name !== 'fid'))
            && fidParams?.length === 1
            // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
            && fidParams.filter(p => !(p.subParam?.not ?? false)).length === 1) { // fid route
            return { name: 'posts/fid', params: { fid: getFidParams(clearedParams)?.[0].value.toString() } };
        }

        return generateParamRoute(clearedUniqueParams, clearedParams); // param route
    };

    const checkParams = async (): Promise<boolean> => {
        const clearedParams = clearedParamsDefaultValue();
        if (currentQueryType.value === 'postID' && isFidParamExists(clearedParams)) {
            getFidParams(clearedParams)?.forEach(param => { param.value = 0 }); // reset fid to default
            notyShow('info', '已移除按帖索引查询所不需要的查询贴吧参数');
            await router.push(generateRoute()); // update route to match new params without fid
        }

        const isRequiredPostTypes = (current: PostType[], required?: ObjValues<RequiredPostTypes>): required is undefined => {
            return required === undefined // not set means this param accepts any post types
                || _.isEmpty(_.difference(current, required[1]));
        };
        const requiredPostTypesToString = (required: NonNullable<ObjValues<RequiredPostTypes>>) => required.join(' | ');
        const postTypes = _.sortBy(uniqueParams.value.postTypes.value);

        invalidParamsIndex.value = []; // reset to prevent duplicate indexes
        // check params required post types, query by post id or fid doesn't restrict on post types
        if (!['postID', 'fid'].includes(currentQueryType.value)) {
            params.value.map(clearParamDefaultValue).forEach((param, paramIndex) => {
                if (param?.name === undefined || param.value === undefined) {
                    invalidParamsIndex.value.push(paramIndex);
                } else {
                    const required = requiredPostTypesKeyByParam[param.name];
                    if (!isRequiredPostTypes(postTypes, required)) {
                        invalidParamsIndex.value.push(paramIndex);
                        notyShow('warning',
                            `第${paramIndex + 1}个${param.name}参数要求帖子类型为${requiredPostTypesToString(required)}`);
                    }
                }
            });
        }

        // check order by required post types
        isOrderByInvalid.value = false;
        const orderBy = uniqueParams.value.orderBy.value;
        if (orderBy in orderByRequiredPostTypes) {
            const required = orderByRequiredPostTypes[orderBy];
            if (!isRequiredPostTypes(postTypes, required)) {
                isOrderByInvalid.value = true;
                notyShow('warning', `排序方式与查询帖子类型要求不匹配<br>当前要求帖子类型为${requiredPostTypesToString(required)}`);
            }
        }

        // return false when there have at least one invalid params
        return _.isEmpty(invalidParamsIndex.value) && !isOrderByInvalid.value;
    };
    const parseRoute = (route: RouteLocationNormalized) => {
        assertRouteNameIsStr(route.name);
        const routeName = routeNameWithoutCursor(route.name);
        uniqueParams.value = _.mapValues(uniqueParams.value, _.unary(fillParamDefaultValue)) as KnownUniqueParams;
        params.value = [];

        ([...postID, 'fid'] as const).forEach((name: PostIDStr | 'fid') => {
            const paramValue = route.params[name];
            if (routeName === `posts/${name}` && !_.isArray(paramValue))
                params.value = [{ name, value: Number(paramValue), subParam: {} }];
        });
        if (_.isArray(route.params.pathMatch))
            parseParamRoute(route.params.pathMatch.filter(i => i !== ''));
    };
    const parseRouteToGetFlattenParams = async (route: RouteLocationNormalized)
    : Promise<ReturnType<typeof flattenParams> | false> => {
        parseRoute(route);
        if (await checkParams())
            return flattenParams();

        return false;
    };

    return { isOrderByInvalid, currentQueryType, generateRoute, parseRouteToGetFlattenParams, ...queryFormWithUniqueParams };
};
