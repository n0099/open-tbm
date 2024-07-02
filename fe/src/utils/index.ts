import { FetchError } from 'ofetch';
import _ from 'lodash';

export type BootstrapColor = 'danger' | 'dark' | 'info' | 'light' | 'muted' | 'primary' | 'secondary' | 'success' | 'warning';
export type SqlDateTimeUtcPlus8 = string; // '2020-10-10 00:11:22'
export type UnixTimestamp = number;
export type Int = number;
export type UInt = number;
export type Float = number;
export type BoolInt = 0 | 1;

export type ObjUnknown = Record<string, unknown>;
export type ObjEmpty = Record<string, never>;

// https://github.com/microsoft/TypeScript/issues/35660
export type Writable<T> = { -readonly [P in keyof T]: T[P] };
export type DeepWritable<T> = { -readonly [P in keyof T]: DeepWritable<T[P]> };

// https://stackoverflow.com/questions/41285211/overriding-interface-property-type-defined-in-typescript-d-ts-file
export type Modify<T, R> = Omit<T, keyof R> & R;
export type ObjValues<T> = T[keyof T];
export type ToPromise<T> = T extends (...args: infer A) => infer R ? (...args: A) => Promise<R> : never;

export type Post = Reply | SubReply | Thread;
export const postType = ['thread', 'reply', 'subReply'] as const;
export type PostType = typeof postType[number];
export const postTypeText = ['主题帖', '回复贴', '楼中楼'] as const;
export type PostTypeText = typeof postTypeText[number];
export type PostTypeTextOf<T> = T extends Thread ? '主题帖'
    : T extends Reply ? '回复贴'
        : T extends SubReply ? '楼中楼' : never;
export const postID = ['tid', 'pid', 'spid'] as const;
export type PostID = typeof postID[number];
export type PostIDOf<T> = T extends Thread ? 'tid' : T extends Reply ? 'pid' : T extends SubReply ? 'spid' : never;
export type Fid = UInt;
export type Tid = UInt;
export type Pid = UInt;
export type Spid = UInt;

export const cursorTemplate = (cursor: Cursor) => (cursor === '' ? '起始页' : `页游标 ${cursor}`);
export const tiebaPostLink = (tid: Tid, pid?: Pid, spid?: Spid) => {
    if (pid !== undefined && spid !== undefined)
        return `https://tieba.baidu.com/p/${tid}?pid=${pid}&cid=${spid}#${spid}`;
    if (pid !== undefined)
        return `https://tieba.baidu.com/p/${tid}?pid=${pid}#${pid}`;

    return `https://tieba.baidu.com/p/${tid}`;
};

export const removeStart = (s: string, remove: string) => (s.startsWith(remove) ? s.slice(remove.length) : s);
export const removeEnd = (s: string, remove: string) => (s.endsWith(remove) ? s.slice(0, -remove.length) : s);
export const boolPropToStr = <T>(object: Record<string, T | boolean>): Record<string, T | string> =>
    _.mapValues(object, i => (_.isBoolean(i) ? i.toString() : i));
export const boolStrToBool = <T>(s: T | 'false' | 'true'): boolean => s === 'true';
export const boolStrPropToBool = <T>(object: Record<string, T | string>): Record<string, T | boolean | string> =>
    _.mapValues(object, i => (_.includes(['true', 'false'], i) ? boolStrToBool(i) : i));
export const isElementNode = (node: Node): node is Element => node.nodeType === Node.ELEMENT_NODE;
export const undefinedOr = <T, TReturn>(value: T | undefined, transformer: (value: T) => TReturn): TReturn | undefined =>
    (value === undefined ? undefined : transformer(value));

// https://stackoverflow.com/questions/71075490/how-to-make-a-structuredclone-of-a-proxy-object/77022014#77022014
export const refDeepClone = <T>(value: T) => structuredClone(toRaw(value));
export const objectWithSameValues = <const TKeys extends string[], const TValue>(keys: TKeys, value: TValue) =>
    _.zipObject(keys, keys.map(() => value)) as Record<TKeys[number], TValue>;

// https://stackoverflow.com/questions/36532307/rem-px-in-javascript/42769683#42769683
// https://gist.github.com/paulirish/5d52fb081b3570c81e3a#calling-getcomputedstyle
export const convertRemToPixels = (rem: number) =>
    rem * parseFloat(getComputedStyle(document.documentElement).fontSize);
export const responseWithError = (error: ApiErrorClass | null) => {
    const event = useRequestEvent();
    if (event) {
        if (error instanceof FetchError)
            setResponseStatus(event, error.statusCode);
        if (error instanceof ApiResponseError)
            setResponseStatus(event, error.fetchError?.statusCode ?? 200);
    }
};
