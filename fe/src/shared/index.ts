export type Iso8601DateTimeUtc0 = string; // "2020-10-10T00:11:22Z"
export type SqlDateTimeUtcPlus8 = string; // '2020-10-10 00:11:22'
namespace GroupByTimeGranularUtcPlus8 {
    type Mix = Day | Hour | Minute | Month | Week | Year;
    type Minute = string; // "2020-10-10 00:11"
    type Hour = string; // "2020-10-10 00:00"
    type Day = string; // "2020-10-10"
    type Week = string; // "2020年第1周"
    type Month = string; // "2020-10"
    type Year = string; // "2020年"
}
export type Int = number;
export type UInt = number;
export type Float = number;
export type UnixTimestamp = number;

export const tiebaUserLink = (username: string) => `https://tieba.baidu.com/home/main?un=${username}`;
