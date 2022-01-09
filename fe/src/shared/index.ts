import Noty from 'noty';
import _ from 'lodash';

export type Iso8601DateTimeUtc0 = string; // "2020-10-10T00:11:22Z"
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

export type BootstrapColors = 'danger' | 'dark' | 'info' | 'light' | 'muted' | 'primary' | 'secondary' | 'success' | 'warning';
export type PostType = 'reply' | 'subReply' | 'thread';
export type PostID = typeof postsID[number];
export const postsID = ['tid', 'pid', 'spid'] as const;
export const postTypeToID = { thread: 'tid', reply: 'pid', subReply: 'spid' };
export type Fid = UInt;
export type Tid = UInt;
export type Pid = UInt;
export type Spid = UInt;

// we can't declare global timeout like `window.noty = new Noty({ timeout: 3000 });` due to https://web.archive.org/web/20201218224752/https://github.com/needim/noty/issues/455
export const notyShow = (type: Noty.Type, text: string) => { new Noty({ timeout: 3000, type, text }).show() };
export const tiebaPostLink = (tid: Tid, pidOrSpid?: Pid | Spid) => {
    if (pidOrSpid !== undefined) return `https://tieba.baidu.com/p/${tid}?pid=${pidOrSpid}#${pidOrSpid}`;
    return `https://tieba.baidu.com/p/${tid}`;
};
export const tiebaUserLink = (username: string) => `https://tieba.baidu.com/home/main?un=${username}`;
export const tiebaUserPortraitUrl = (portrait: string) => `https://himg.bdimg.com/sys/portrait/item/${portrait}.jpg`; // use /sys/portraith for high-res image

export const removeStart = (s: string, remove: string) => (s.startsWith(remove) ? s.slice(remove.length) : s);
export const removeEnd = (s: string, remove: string) => (s.endsWith(remove) ? s.slice(0, -remove.length) : s);
export const boolPropToStr = <T>(object: Record<string, T | boolean>): Record<string, T | string> =>
    _.mapValues(object, i => (_.isBoolean(i) ? String(i) : i));
export const boolStrToBool = <T>(s: T | 'false' | 'true'): boolean => s === 'true';
export const boolStrPropToBool = <T>(object: Record<string, T | string>): Record<string, T | boolean | string> =>
    _.mapValues(object, i => (_.includes(['true', 'false'], i) ? boolStrToBool(i) : i));
export const emitEventStrValidator = (p: string) => _.isString(p);
export const emitEventNumValidator = (p: number) => _.isNumber(p);
