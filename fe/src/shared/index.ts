export type Iso8601DateTimeUtc0 = string; // "2020-10-10T00:11:22Z"
export type SqlDateTimeUtcPlus8 = string; // '2020-10-10 00:11:22'
export type Int = number;
export type UInt = number;
export type Float = number;
export type UnixTimestamp = number;

export const tiebaUserLink = (username: string) => `https://tieba.baidu.com/home/main?un=${username}`;
