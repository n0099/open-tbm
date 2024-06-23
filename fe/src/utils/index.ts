import type { LocationAsRelativeRaw } from 'vue-router';
import Noty from 'noty';
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

// we can't declare global timeout like `window.noty = new Noty({...});`
// due to https://web.archive.org/web/20201218224752/https://github.com/needim/noty/issues/455
export const notyShow = (type: Noty.Type, text: string) => { new Noty({ timeout: 5000, type, text }).show() };
export const cursorTemplate = (cursor: Cursor) => (cursor === '' ? '起始页' : `页游标 ${cursor}`);

export const tiebaPostLink = (tid: Tid, pid?: Pid, spid?: Spid) => {
    if (pid !== undefined && spid !== undefined)
        return `https://tieba.baidu.com/p/${tid}?pid=${pid}&cid=${spid}#${spid}`;
    if (pid !== undefined)
        return `https://tieba.baidu.com/p/${tid}?pid=${pid}#${pid}`;

    return `https://tieba.baidu.com/p/${tid}`;
};
export const toUserProfileUrl = (user: Partial<Pick<User, 'name' | 'portrait'>>) =>
    (_.isEmpty(user.portrait)
        ? `https://tieba.baidu.com/home/main?un=${user.name}`
        : `https://tieba.baidu.com/home/main?id=${user.portrait}`);
export const toUserPortraitImageUrl = (portrait: string) =>
    `https://himg.bdimg.com/sys/portrait/item/${portrait}.jpg`; // use /sys/portraith for high-res image
export const toUserRoute = (uid: BaiduUserID): LocationAsRelativeRaw =>
    ({ name: 'users/uid', params: { uid: uid.toString() } });

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

// https://stackoverflow.com/questions/36532307/rem-px-in-javascript/42769683#42769683
// https://gist.github.com/paulirish/5d52fb081b3570c81e3a#calling-getcomputedstyle
export const convertRemToPixels = (rem: number) =>
    rem * parseFloat(getComputedStyle(document.documentElement).fontSize);

// https://stackoverflow.com/questions/986937/how-can-i-get-the-browsers-scrollbar-sizes/986977#986977
export const scrollBarWidth = computed(() => {
    if (!import.meta.client)
        return;
    const inner = document.createElement('p');
    inner.style.width = '100%';
    inner.style.height = '200px';

    const outer = document.createElement('div');
    outer.style.position = 'absolute';
    outer.style.top = '0px';
    outer.style.left = '0px';
    outer.style.visibility = 'hidden';
    outer.style.width = '200px';
    outer.style.height = '150px';
    outer.style.overflow = 'hidden';
    outer.append(inner);

    document.body.append(outer);
    const w1 = inner.offsetWidth;
    outer.style.overflow = 'scroll';
    let w2 = inner.offsetWidth;
    if (w1 === w2)
        w2 = outer.clientWidth;
    outer.remove();

    return `${w1 - w2}px`;
});
