import _ from 'lodash';

export type Param = ObjValues<KnownParams>;
export const isDateTimeParam = (param: Param): param is KnownDateTimeParams =>
    (paramsNameKeyByType.dateTime as Writable<typeof paramsNameKeyByType.dateTime> as string[]).includes(param.name);
export const isTextParam = (param: Param): param is KnownTextParams =>
    (paramsNameKeyByType.text as Writable<typeof paramsNameKeyByType.text> as string[]).includes(param.name);
export const isPostIDParam = (param: Param): param is AddNameToParam<PostID, NamelessParamNumeric> =>
    (postID as Writable<typeof postID> as string[]).includes(param.name);

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

    const getCurrentQueryType = () => {
        const clearedParams = clearedParamsDefaultValue(); // not including unique params
        if (_.isEmpty(clearedParams)) { // is there no other params
            // ignore the post type param since index query (postID or fid) doesn't restrict them
            const clearedUniqueParams = _.omit(clearedUniqueParamsDefaultValue(), 'postTypes');
            if (_.isEmpty(clearedUniqueParams)) {
                return 'empty'; // only fill unique param postTypes and/or orderBy doesn't query anything
            } else if (clearedUniqueParams.fid !== undefined) {
                // note when query with postTypes and/or orderBy param, the route will go params instead of fid
                return 'fid';
            }
        }

        // is there no other params except post id params
        if (_.isEmpty(_.reject(clearedParams, isPostIDParam))

            // is there only one post id param
            && _.filter(clearedParams, isPostIDParam).length === 1

            // is all post ID params doesn't own any sub param
            && _.chain(clearedParams).map('subParam').filter().isEmpty().value())
            return 'postID';

        return 'search';
    };
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
        const currentQueryType = getCurrentQueryType();
        switch (currentQueryType) {
            case 'empty':
                notyShow('warning', '请选择贴吧或/并输入查询参数<br />勿只选择帖子类型参数');

                return false; // exit early
            case 'postID':
                if (clearedUniqueParams.fid !== undefined) {
                    uniqueParams.value.fid.value = 0; // reset fid to default,
                    notyShow('info', '已移除按帖索引查询所不需要的查询贴吧参数');
                    await router.push(generateRoute()); // update route to match new params without fid
                }
                break;
            case 'search':
                if (clearedUniqueParams.fid === undefined) {
                    isFidInvalid.value = true; // search query require fid param
                    notyShow('warning', '搜索查询必须指定查询贴吧');
                }
                break;
            case 'fid':
        }

        const isRequiredPostTypes = (current: PostType[], required?: RequiredPostTypes[string]): required is undefined => {
            if (required === undefined)
                return true; // not set means this param accepts any post types
            required[1] = _.sortBy(required[1]);
            if (required[0] === 'SUB' && _.isEmpty(_.difference(current, required[1])))
                return true;

            return required[0] === 'ALL' && _.isEqual(required[1], current);
        };
        const requiredPostTypesToString = (required: NonNullable<RequiredPostTypes[string]>) =>
            required[1].join(required[0] === 'SUB' ? ' | ' : ' & ');
        const postTypes = _.sortBy(uniqueParams.value.postTypes.value);

        // check params required post types, index query doesn't restrict on post types
        invalidParamsIndex.value = []; // reset to prevent duplicate indexes
        if (currentQueryType !== 'postID' && currentQueryType !== 'fid') {
            /** we don't {@link Array.filter()} here for post types validate */
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
                notyShow('warning', `排序方式与查询帖子类型要求不匹配<br />当前要求帖子类型为${requiredPostTypesToString(required)}`);
            }
        }

        // return false when there have at least one invalid params
        return _.isEmpty(invalidParamsIndex.value) && !(isOrderByInvalid.value || isFidInvalid.value);
    };
    const parseRoute = (route: RouteLocationNormalized) => {
        assertRouteNameIsStr(route.name);
        uniqueParams.value = _.mapValues(uniqueParams.value, _.unary(fillParamDefaultValue)) as KnownUniqueParams;
        params.value = [];
        const routeName = removeEnd(route.name, routeNameSuffix.cursor);

        // parse route path to params
        if (routeName === 'posts/param' && _.isArray(route.params.pathMatch)) {
            parseParamRoute(route.params.pathMatch); // omit the cursor param from route full path
        } else if (routeName === 'posts/fid' && !_.isArray(route.params.fid)) {
            uniqueParams.value.fid.value = parseInt(route.params.fid);
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

    return { isOrderByInvalid, isFidInvalid, getCurrentQueryType, generateRoute, parseRouteToGetFlattenParams, ...queryFormWithUniqueParams };
};
