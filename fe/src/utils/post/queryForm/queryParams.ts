import _ from 'lodash';

export type RequiredPostTypes = Record<string, PostType[] | undefined>;
export const requiredPostTypesKeyByParam: RequiredPostTypes = {
    pid: ['reply', 'subReply'],
    spid: ['subReply'],
    postContent: ['reply', 'subReply'],
    replySubReplyCount: ['reply'],
    authorExpGrade: ['reply', 'subReply'],
    ...keysWithSameValue([
        'latestReplyPostedAt',
        'threadTitle',
        'threadViewCount',
        'threadShareCount',
        'threadReplyCount',
        'threadProperties',
        'latestReplierUid',
        'latestReplierName',
        'latestReplierDisplayName',
        'latestReplierGender'
    ], ['thread'])
};
export const orderByRequiredPostTypes: RequiredPostTypes = {
    pid: ['reply', 'subReply'],
    spid: ['subReply']
};

export const paramNamesKeyByType = {
    numeric: [
        'tid',
        'pid',
        'spid',
        'threadViewCount',
        'threadShareCount',
        'threadReplyCount',
        'replySubReplyCount',
        'authorUid',
        'authorExpGrade',
        'latestReplierUid'
    ],
    text: [
        'threadTitle',
        'postContent',
        'authorName',
        'authorDisplayName',
        'latestReplierName',
        'latestReplierDisplayName'
    ],
    dateTime: [
        'postedAt',
        'latestReplyPostedAt'
    ],
    gender: [
        'authorGender',
        'latestReplierGender'
    ]
} as const;

export const numericParamSubParamRangeValues = ['<', '=', '>', 'BETWEEN', 'IN'] as const;
export interface NamelessParamNumeric {
    value: string, // support subParam.range === BETWEEN or IN
    subParam: { range: ArrayElement<typeof numericParamSubParamRangeValues> }
}
export const textParamSubParamMatchByValues = ['explicit', 'implicit', 'regex'] as const;
export interface NamelessParamText {
    value: string,
    subParam: {
        matchBy: ArrayElement<typeof textParamSubParamMatchByValues>,
        spaceSplit: boolean
    }
}
interface NamelessParamDateTime { value: string, subParam: { range: undefined } }
interface NamelessParamGender { value: '0' | '1' | '2' }
interface NamelessParamsOther {
    fid: { value: Fid },
    threadProperties: { value: Array<'good' | 'sticky'> },
    authorManagerType: { value: ForumModeratorType | 'NULL' }
}

export type AddNameToParam<Name extends UnknownParam['name'], NamelessParam extends Partial<UnknownParam>> =
Omit<NamelessParam, 'subParam'>
    & {
        name: Name,
        value: unknown,
        subParam: ObjEmpty
        | { not?: boolean }

            // https://stackoverflow.com/questions/68232762/check-if-type-is-the-unknown-type
            & (unknown extends NamelessParam['subParam']
                ? ObjEmpty
                : NamelessParam['subParam'])
    };
export type KnownParams = { [P in keyof NamelessParamsOther]: AddNameToParam<P, NamelessParamsOther[P]> }
    & { [P in ArrayElement<typeof paramNamesKeyByType.numeric>]: AddNameToParam<P, NamelessParamNumeric> }
    & { [P in ArrayElement<typeof paramNamesKeyByType.text>]: AddNameToParam<P, NamelessParamText> }
    & { [P in ArrayElement<typeof paramNamesKeyByType.dateTime>]: AddNameToParam<P, NamelessParamDateTime> }
    & { [P in ArrayElement<typeof paramNamesKeyByType.gender>]: AddNameToParam<P, NamelessParamGender> };
export type KnownNumericParams = KnownParams[ArrayElement<typeof paramNamesKeyByType.numeric>];
export type KnownTextParams = KnownParams[ArrayElement<typeof paramNamesKeyByType.text>];
export type KnownDateTimeParams = KnownParams[ArrayElement<typeof paramNamesKeyByType.dateTime>];
export interface KnownUniqueParams extends Record<string, UnknownParam> {
    postTypes: { name: 'postTypes', value: PostType[], subParam: ObjEmpty },
    orderBy: {
        name: 'orderBy',
        value: PostIDStr | 'default' | 'postedAt',
        subParam: { direction: 'ASC' | 'default' | 'DESC' }
    }
}

const paramMetadataKeyByType: Record<'array' | 'numeric' | 'text' | 'dateTime' | 'gender', {
    default?: NamelessUnknownParam,
    preprocessor?: ParamPreprocessorOrWatcher,
    watcher?: ParamPreprocessorOrWatcher
}> = { // mutating param object will sync changes
    array: {
        preprocessor(param) {
            if (_.isString(param.value))
                param.value = param.value.split(',');
        }
    },
    numeric: { default: { subParam: { range: '=' } } },
    text: {
        default: { subParam: { matchBy: 'explicit', spaceSplit: false } },
        preprocessor(param) {
            param.subParam.spaceSplit = boolStrToBool(param.subParam.spaceSplit);
        },
        watcher(param) {
            if (param.subParam.matchBy === 'regex')
                param.subParam.spaceSplit = false;
        }
    },
    dateTime: {
        default: { subParam: { range: undefined } },
        preprocessor(param) {
            if (!_.isString(param.value))
                return;
            param.subParam.range = param.value.split(',');
        },
        watcher(param) {
            // combine datetime range into root param's value
            param.value = _.isArray(param.subParam.range) ? param.subParam.range.join(',') : '';
        }
    },
    gender: { default: { value: '0' } }
};
const paramsDefaultValue = {
    fid: { value: 0, subParam: {} },
    postTypes: { value: postType, subParam: {} },
    orderBy: { value: 'default', subParam: { direction: 'default' } },
    threadProperties: { value: [] },
    authorManagerType: { value: 'NULL' },
    ..._.mapValues(_.keyBy(paramNamesKeyByType.numeric), () =>
        paramMetadataKeyByType.numeric.default),
    ..._.mapValues(_.keyBy(paramNamesKeyByType.text), () =>
        paramMetadataKeyByType.text.default),
    ..._.mapValues(_.keyBy(paramNamesKeyByType.dateTime), () =>
        paramMetadataKeyByType.dateTime.default),
    ..._.mapValues(_.keyBy(paramNamesKeyByType.gender), () =>
        paramMetadataKeyByType.gender.default)
} as const;
const useQueryFormDependency: Parameters<typeof useQueryForm>[0] = {
    paramsDefaultValue,
    paramsPreprocessor: {
        postTypes: paramMetadataKeyByType.array.preprocessor,
        threadProperties: paramMetadataKeyByType.array.preprocessor,
        ..._.mapValues(_.keyBy(paramNamesKeyByType.text), () =>
            paramMetadataKeyByType.text.preprocessor),
        ..._.mapValues(_.keyBy(paramNamesKeyByType.dateTime), () =>
            paramMetadataKeyByType.dateTime.preprocessor)
    },
    paramsWatcher: {
        ..._.mapValues(_.keyBy(paramNamesKeyByType.text), () =>
            paramMetadataKeyByType.text.watcher),
        ..._.mapValues(_.keyBy(paramNamesKeyByType.dateTime), () =>
            paramMetadataKeyByType.dateTime.watcher),
        orderBy(param) {
            if (param.value === 'default' && param.subParam.direction !== 'default') { // reset to default
                param.subParam = { ...param.subParam, direction: 'default' };
            }
        }
    }
};

// must get invoked with in the setup of component
export const useQueryFormWithUniqueParams = () => {
    const ret = useQueryForm<KnownUniqueParams, KnownParams>(useQueryFormDependency);
    ret.uniqueParams.value = {
        postTypes: {
            name: 'postTypes',
            ...paramsDefaultValue.postTypes as DeepWritable<typeof paramsDefaultValue.postTypes>
        },
        orderBy: { name: 'orderBy', ...paramsDefaultValue.orderBy }
    };

    return ret;
};
