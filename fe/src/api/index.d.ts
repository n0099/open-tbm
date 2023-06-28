import type { SelectTiebaUserParams } from '@/components/SelectTiebaUser.vue';
import type { BoolInt, Fid, Float, Int, ObjUnknown, Pid, Spid, Tid, UInt, UnixTimestamp } from '@/shared';
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
export interface ApiStatusQueryParam {
    timeGranularity: 'day' | 'hour' | 'minute',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}

interface TimeCountPair { time: Mix, count: UInt }
export interface ApiStatsForumPostCount {
    thread: TimeCountPair[],
    reply: TimeCountPair[],
    subReply: TimeCountPair[]
}
export interface ApiStatsForumPostCountQueryParam {
    fid: Fid,
    timeGranularity: 'day' | 'hour' | 'minute' | 'month' | 'week' | 'year',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}

export type Pagination = { [P in 'currentPage' | 'firstItem' | 'itemCount']: number };
interface ApiQueryParamPagination { page?: number }
export type BaiduUserID = Int;
export type TiebaUserGender = 0 | 1 | 2 | null;
export type TiebaUserGenderQueryParam = '0' | '1' | '2' | 'NULL';
export interface TiebaUserRecord extends LaravelEloquentRecordsCommonTimestampFields {
    uid: BaiduUserID,
    name: string | null,
    displayName: string | null,
    portrait: string,
    portraitUpdatedAt: UInt | null,
    gender: TiebaUserGender,
    fansNickname: string | null,
    icon: ObjUnknown[] | null,
    ipGeolocation: string | null
}
export interface ApiUsersQuery {
    pages: Pagination,
    users: TiebaUserRecord[]
}
export type ApiUsersQueryQueryParam = ApiQueryParamPagination & SelectTiebaUserParams & { gender?: TiebaUserGenderQueryParam };

type LaravelEloquentRecordsCommonTimestampFields = { [P in 'createdAt' | 'updatedAt']: UnixTimestamp };
interface Post extends Agree, LaravelEloquentRecordsCommonTimestampFields {
    tid: Tid,
    authorUid: BaiduUserID,
    postedAt: UnixTimestamp,
    lastSeenAt: UnixTimestamp
}
interface Agree {
    agreeCount: Int,
    disagreeCount: Int
}
export type AuthorManagerType = 'assist' | 'manager' | 'picadmin' | 'voiceadmin';
export type AuthorExpGrade = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 | 16 | 17 | 18;
export interface ThreadRecord extends Post {
    threadType: UInt | 1024 | 1040,
    stickyType: 'membertop' | 'top',
    topicType: '' | 'text',
    isGood: BoolInt,
    title: string,
    authorPhoneType: string,
    latestReplyPostedAt: UnixTimestamp,
    latestReplierUid: BaiduUserID,
    replyCount: UInt,
    viewCount: UInt,
    shareCount: UInt,
    zan: ObjUnknown | null,
    geolocation: ObjUnknown | null
}
export interface ReplyRecord extends Post {
    pid: Pid,
    floor: UInt,
    content: string, // original json convert to html string via be/app/resources/views/renderPostContent.blade.php
    authorExpGrade: AuthorExpGrade,
    subReplyCount: UInt,
    isFold: UInt | 0 | 6,
    geolocation: ObjUnknown | null
}
export interface SubReplyRecord extends Post {
    pid: Pid,
    spid: Spid,
    content: string, // original json convert to html string via be/app/resources/views/renderPostContent.blade.php
    authorExpGrade: AuthorExpGrade
}

export type ApiPostsQuery = ApiUsersQuery & {
    forum: Pick<ApiForumList[number], 'fid' | 'name'>,
    threads: Array<ThreadRecord & {
        replies: Array<ReplyRecord & {
            subReplies: SubReplyRecord[]
        }>
    }>
};
export type ApiPostsQueryQueryParam = ApiQueryParamPagination & { query: string };
