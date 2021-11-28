import type { SelectTiebaUserParams } from '@/components/SelectTiebaUser.vue';
import type { Float, ObjUnknown, UInt, UnixTimestamp } from '@/shared';
import type { Mix } from '@/shared/groupBytimeGranularityUtcPlus8';

export interface ApiError { errorCode: number, errorInfo: Record<string, unknown[]> | string }

export type ApiForumList = Array<{
    id: number,
    fid: number,
    name: string,
    isCrawling: number
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
    fid: UInt,
    timeGranularity: 'day' | 'hour' | 'minute' | 'month' | 'week' | 'year',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}

export type Pagination = { [P in 'currentItems' | 'currentPage' | 'firstItem']: number };
interface ApiQPPagination { page?: number }
export type TiebaUserGender = 0 | 1 | 2 | null;
export type TiebaUserGenderQP = '0' | '1' | '2' | 'NULL';
export interface TiebaUserInfo {
    uid: number,
    avatarUrl: string,
    name: string | null,
    displayName: string | null,
    fansNickname: string | null,
    gender: TiebaUserGender,
    iconInfo: ObjUnknown[] | null
}
export interface ApiUsersQuery {
    pages: Pagination,
    users: TiebaUserInfo[]
}
export type ApiUsersQueryQP = ApiQPPagination & SelectTiebaUserParams & { gender?: TiebaUserGenderQP };

export interface ApiPostsQuery {
    pages: Pagination
}
export type ApiPostsQueryQP = ApiQPPagination & { query: string };
