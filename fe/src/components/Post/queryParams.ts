import type { Param, ParamPartialValue, ParamPreprocessorOrWatcher } from './useQueryForm';
import useQueryForm from './useQueryForm';
import type { DeepWritable, Fid, ObjEmpty, PostID, PostType } from '@/shared';
import { boolStrToBool } from '@/shared';
import _ from 'lodash';

// value of [0] will be either ALL: require exactly same post types, or SUB: requiring a subset of types
export type RequiredPostTypes = Record<string, ['ALL' | 'SUB', PostType[]] | undefined>;
export const paramsRequiredPostTypes: RequiredPostTypes = {
    pid: ['SUB', ['reply', 'subReply']],
    spid: ['ALL', ['subReply']],
    latestReplyTime: ['ALL', ['thread']],
    threadTitle: ['ALL', ['thread']],
    postContent: ['SUB', ['reply', 'subReply']],
    threadViewNum: ['ALL', ['thread']],
    threadShareNum: ['ALL', ['thread']],
    threadReplyNum: ['ALL', ['thread']],
    replySubReplyNum: ['ALL', ['reply']],
    threadProperties: ['ALL', ['thread']],
    authorExpGrade: ['SUB', ['reply', 'subReply']],
    latestReplierUid: ['ALL', ['thread']],
    latestReplierName: ['ALL', ['thread']],
    latestReplierDisplayName: ['ALL', ['thread']],
    latestReplierGender: ['ALL', ['thread']]
};
export const orderByRequiredPostTypes: RequiredPostTypes = {
    pid: ['SUB', ['reply', 'subReply']],
    spid: ['SUB', ['subReply']]
};
const paramTypes: { [P in 'array' | 'dateTimeRange' | 'numeric' | 'textMatch']: {
    default?: ParamPartialValue,
    preprocessor?: ParamPreprocessorOrWatcher,
    watcher?: ParamPreprocessorOrWatcher
} } = { // mutating param object will sync changes
    array: {
        preprocessor: param => {
            if (_.isString(param.value)) param.value = param.value.split(',');
        }
    },
    numeric: { default: { subParam: { range: '=' } } },
    textMatch: {
        default: { subParam: { matchBy: 'implicit', spaceSplit: false } },
        preprocessor: param => {
            param.subParam.spaceSplit = boolStrToBool(param.subParam.spaceSplit);
        },
        watcher: param => {
            if (param.subParam.matchBy === 'regex') param.subParam.spaceSplit = false;
        }
    },
    dateTimeRange: {
        default: { subParam: { range: undefined } },
        preprocessor: param => {
            if (!_.isString(param.value)) return;
            param.subParam.range = param.value.split(',');
        },
        watcher: param => {
            // combine datetime range into root param's value
            param.value = _.isArray(param.subParam.range) ? param.subParam.range.join(',') : '';
        }
    }
};
export const paramsNameByType = {
    numeric: [
        'tid',
        'pid',
        'spid',
        'threadViewNum',
        'threadShareNum',
        'threadReplyNum',
        'replySubReplyNum',
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
        'postTime',
        'latestReplyTime'
    ]
} as const;
interface ParamTypeNum { value: string, subParam: { range: '<' | '=' | '>' | 'BETWEEN' | 'IN' } }
interface ParamTypeText { value: string, subParam: { matchBy: 'eegex' | 'explicit' | 'implicit', spaceSplit: boolean } }
interface ParamTypeDateTime { value: string, subParam: { range: undefined } }
interface ParamTypeGender { value: '0' | '1' | '2' }
interface ParamTypeOther {
    threadProperties: { value: Array<'good' | 'sticky'> },
    authorManagerType: { value: 'assist' | 'manager' | 'NULL' | 'voiceadmin' }
}
interface ParamsCommon<P> { name: P, subParam: ObjEmpty }
export type Params = { [P in 'authorGender' | 'latestReplierGender']: ParamsCommon<P> & ParamTypeGender }
& { [P in keyof ParamTypeOther]: ParamsCommon<P> & ParamTypeOther[P] }
& { [P in typeof paramsNameByType.dateTime[number]]: ParamsCommon<P> & ParamTypeDateTime }
& { [P in typeof paramsNameByType.numeric[number]]: ParamsCommon<P> & ParamTypeNum }
& { [P in typeof paramsNameByType.text[number]]: ParamsCommon<P> & ParamTypeText };
const paramsDefaultValue = {
    fid: { value: 0, subParam: {} },
    postTypes: { value: ['thread', 'reply', 'subReply'], subParam: {} },
    orderBy: { value: 'default', subParam: { direction: 'default' } },
    threadProperties: { value: [] },
    authorManagerType: { value: 'NULL' },
    authorGender: { value: '0' },
    latestReplierGender: { value: '0' },
    ..._.mapValues(_.keyBy(paramsNameByType.numeric), () => paramTypes.numeric.default),
    ..._.mapValues(_.keyBy(paramsNameByType.text), () => paramTypes.textMatch.default),
    ..._.mapValues(_.keyBy(paramsNameByType.dateTime), () => paramTypes.dateTimeRange.default)
} as const;
export const useQueryFormLateBinding: Parameters<typeof useQueryForm>[0] = {
    paramsDefaultValue,
    paramsPreprocessor: {
        postTypes: paramTypes.array.preprocessor,
        threadProperties: paramTypes.array.preprocessor,
        ..._.mapValues(_.keyBy(paramsNameByType.text), () => paramTypes.textMatch.preprocessor),
        ..._.mapValues(_.keyBy(paramsNameByType.dateTime), () => paramTypes.dateTimeRange.preprocessor)
    },
    paramsWatcher: {
        ..._.mapValues(_.keyBy(paramsNameByType.text), () => paramTypes.textMatch.watcher),
        ..._.mapValues(_.keyBy(paramsNameByType.dateTime), () => paramTypes.dateTimeRange.watcher),
        orderBy(param) {
            if (param.value === 'default' && param.subParam.direction !== 'default') { // reset to default
                param.subParam = { ...param.subParam, direction: 'default' };
            }
        }
    }
};
export interface UniqueParams extends Record<string, Param> {
    fid: { name: 'fid', value: Fid, subParam: ObjEmpty },
    postTypes: { name: 'postTypes', value: PostType[], subParam: ObjEmpty },
    orderBy: { name: 'orderBy', value: PostID | 'default' | 'postTime', subParam: { direction: 'ASC' | 'default' | 'DESC' } }
}
// must get invoked with in the setup() of component
export const useQueryFormWithUniqueParams = () => {
    const ret = useQueryForm<UniqueParams, Params>(useQueryFormLateBinding);
    ret.state.uniqueParams = {
        fid: { name: 'fid', ...paramsDefaultValue.fid },
        postTypes: { name: 'postTypes', ...paramsDefaultValue.postTypes as DeepWritable<typeof paramsDefaultValue.postTypes> },
        orderBy: { name: 'orderBy', ...paramsDefaultValue.orderBy }
    };
    return ret;
};
