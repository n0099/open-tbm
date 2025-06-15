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
    const isFidInvalid = ref(false);
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

    const currentQueryType = computed(() => {
        const clearedParams = clearedParamsDefaultValue(); // not including unique params
        if (_.isEmpty(clearedParams)) {
            const clearedUniqueParams = clearedUniqueParamsDefaultValue();
            if (_.isEmpty(clearedUniqueParams))
                return 'empty';
            if (clearedUniqueParams.fid !== undefined)
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
                const postIDParam = _.filter(clearedParams, p => p.name === postIDName);
                if (_.isEmpty(_.reject(clearedParams, p => p.name === postIDName)) // is there no other params
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
        if (clearedUniqueParams.fid !== undefined
            && _.isEmpty(clearedParams)
            && _.isEmpty(_.omit(clearedUniqueParams, 'fid'))) { // fid route
            return { name: 'posts/fid', params: { fid: clearedUniqueParams.fid.value.toString() } };
        }

        return generateParamRoute(clearedUniqueParams, clearedParams); // param route
    };

    const checkParams = async (): Promise<boolean> => {
        // check query type
        isFidInvalid.value = false;
        const clearedUniqueParams = clearedUniqueParamsDefaultValue();
        if (currentQueryType.value === 'postID' && clearedUniqueParams.fid !== undefined) {
            uniqueParams.value.fid.value = 0; // reset fid to default,
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
        return _.isEmpty(invalidParamsIndex.value) && !(isOrderByInvalid.value || isFidInvalid.value);
    };
    const parseRoute = (route: RouteLocationNormalized) => {
        assertRouteNameIsStr(route.name);
        const routeName = routeNameWithoutCursor(route.name);
        uniqueParams.value = _.mapValues(uniqueParams.value, _.unary(fillParamDefaultValue)) as KnownUniqueParams;
        params.value = [];

        // parse route path to params
        if (routeName === 'posts/param' && _.isArray(route.params.pathMatch)) {
            parseParamRoute(route.params.pathMatch); // omit the cursor param from route full path
        } else if (routeName === 'posts/fid' && !_.isArray(route.params.fid)) {
            uniqueParams.value.fid.value = Number(route.params.fid);
        } else { // post id routes
            uniqueParams.value = _.mapValues(uniqueParams.value, param =>
                fillParamDefaultValue(param, true)) as KnownUniqueParams; // reset to default
            params.value = _.map(_.omit(route.params, 'pathMatch', 'cursor'), (value, name) =>
                fillParamDefaultValue({ name, value }));
        }
    };
    const parseRouteToGetFlattenParams = async (route: RouteLocationNormalized)
    : Promise<ReturnType<typeof flattenParams> | false> => {
        parseRoute(route);
        if (await checkParams())
            return flattenParams();

        return false;
    };

    return { isOrderByInvalid, isFidInvalid, currentQueryType, generateRoute, parseRouteToGetFlattenParams, ...queryFormWithUniqueParams };
};
