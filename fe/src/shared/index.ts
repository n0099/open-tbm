import _ from 'lodash';

export type Iso8601DateTimeUtc0 = string; // "2020-10-10T00:11:22Z"
export type SqlDateTimeUtcPlus8 = string; // '2020-10-10 00:11:22'
export type Int = number;
export type UInt = number;
export type Float = number;
export type UnixTimestamp = number;

export const tiebaUserLink = (username: string) => `https://tieba.baidu.com/home/main?un=${username}`;
export const tiebaUserPortraitUrl = (portrait: string) => `https://himg.bdimg.com/sys/portrait/item/${portrait}.jpg`;
export const boolPropToStr = <T>(object: Record<string, T | boolean>): Record<string, T | string> =>
    _.mapValues(object, i => (_.isBoolean(i) ? String(i) : i));
export const boolStrPropToBool = <T>(object: Record<string, T | string>): Record<string, T | boolean | string> =>
    _.mapValues(object, i => (_.includes(['true', 'false'], i) ? i === 'true' : i));
