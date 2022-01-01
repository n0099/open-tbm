import type { SelectTiebaUserParams } from '@/components/SelectTiebaUser.vue';
import type { BoolInt, Fid, Float, Int, Iso8601DateTimeUtc0, ObjUnknown, Pid, Spid, SqlDateTimeUtcPlus8, Tid, UInt, UnixTimestamp } from '@/shared';
import type { Mix } from '@/shared/groupBytimeGranularityUtcPlus8';

export interface ApiError { errorCode: number, errorInfo: Record<string, unknown[]> | string }

export type ApiForumList = Array<{
    id: number,
    fid: Fid,
    name: string,
    isCrawling: BoolInt
}>;

export type ApiStatus = Array<{
    startTime: UnixTimestamp,
    queueTiming: Float,
    webRequestTiming: Float,
    savePostsTiming: Float,
    webRequestTimes: UInt,
    parsedPostTimes: UInt,
    parsedUserTimes: UInt
}>;
export interface ApiStatusQP {
    timeGranularity: 'day' | 'hour' | 'minute',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}

interface TimeCountPair { time: Mix, count: UInt }
export interface ApiStatsForumPostsCount {
    thread: TimeCountPair[],
    reply: TimeCountPair[],
    subReply: TimeCountPair[]
}
export interface ApiStatsForumPostsCountQP {
    fid: Fid,
    timeGranularity: 'day' | 'hour' | 'minute' | 'month' | 'week' | 'year',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}

export type Pagination = { [P in 'currentPage' | 'firstItem' | 'itemsCount']: number };
interface ApiQPPagination { page?: number }
export type BaiduUserID = Int;
export type TiebaUserGender = 0 | 1 | 2 | null;
export type TiebaUserGenderQP = '0' | '1' | '2' | 'NULL';
export interface TiebaUserRecord {
    uid: BaiduUserID,
    avatarUrl: string,
    name: string | null,
    displayName: string | null,
    fansNickname: string | null,
    gender: TiebaUserGender,
    iconInfo: ObjUnknown[] | null
}
export interface ApiUsersQuery {
    pages: Pagination,
    users: TiebaUserRecord[]
}
export type ApiUsersQueryQP = ApiQPPagination & SelectTiebaUserParams & { gender?: TiebaUserGenderQP };

type LaravelEloquentRecordsCommonTimestampFields = { [P in 'created_at' | 'updated_at']: Iso8601DateTimeUtc0 };
export type AuthorManagerType = 'assist' | 'manager' | 'picadmin' | 'voiceadmin';
export interface ThreadRecord extends LaravelEloquentRecordsCommonTimestampFields {
    tid: Tid,
    firstPid: Pid,
    threadType: UInt | number | 1024 | 1040,
    stickyType: 'membertop' | 'top',
    isGood: BoolInt,
    topicType: 'normal',
    title: string,
    authorUid: BaiduUserID,
    authorManagerType: AuthorManagerType,
    postTime: SqlDateTimeUtcPlus8,
    latestReplierUid: BaiduUserID,
    latestReplyTime: SqlDateTimeUtcPlus8,
    replyNum: UInt,
    viewNum: UInt,
    shareNum: UInt,
    location: ObjUnknown | null,
    agreeInfo: ObjUnknown | null,
    zanInfo: ObjUnknown | null
}
export type AuthorExpGrade = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 | 16 | 17 | 18;
export interface ReplyRecord extends LaravelEloquentRecordsCommonTimestampFields {
    tid: Tid,
    pid: Pid,
    floor: UInt,
    content: string, // original json convert to html string via be/app/resources/views/formatPostJsonContent.blade.php
    authorUid: BaiduUserID,
    authorManagerType: AuthorManagerType,
    authorExpGrade: AuthorExpGrade,
    subReplyNum: UInt,
    postTime: SqlDateTimeUtcPlus8,
    isFold: 0 | 6,
    location: ObjUnknown | null,
    agreeInfo: ObjUnknown | null,
    signInfo: ObjUnknown | null,
    tailInfo: ObjUnknown | null
}
export interface SubReplyRecord extends LaravelEloquentRecordsCommonTimestampFields {
    tid: Tid,
    pid: Pid,
    spid: Spid,
    content: string, // original json convert to html string via be/app/resources/views/formatPostJsonContent.blade.php
    authorUid: BaiduUserID,
    authorManagerType: AuthorManagerType,
    authorExpGrade: AuthorExpGrade,
    postTime: SqlDateTimeUtcPlus8
}
export type PostRecord = ReplyRecord | SubReplyRecord | ThreadRecord;

export type ApiPostsQuery = ApiUsersQuery & {
    forum: Pick<ApiForumList[number], 'fid' | 'name'>,
    threads: Array<ThreadRecord & {
        replies: Array<ReplyRecord & {
            subReplies: SubReplyRecord[]
        }>
    }>
};
export type ApiPostsQueryQP = ApiQPPagination & { query: string };
