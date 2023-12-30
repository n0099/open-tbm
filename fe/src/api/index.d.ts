import type { Reply, SubReply, Thread } from './post';
import type { TiebaUser, TiebaUserGenderQueryParam } from './user';
import type { SelectTiebaUserParams } from '@/components/widgets/selectTiebaUser';
import type { BoolInt, Fid, Float, PostType, UInt, UnixTimestamp } from '@/shared';
import type { Mix } from '@/shared/groupBytimeGranularityUtcPlus8';

export interface ApiError { errorCode: number, errorInfo: Record<string, unknown[]> | string }

export type ApiForumList = Array<{
    id: UInt,
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

interface ApiQueryParamCursorPagination { cursor?: Cursor }
export interface ApiUsers {
    pages: CursorPagination,
    users: TiebaUser[]
}
export type ApiUsersParam =
    ApiQueryParamCursorPagination & SelectTiebaUserParams & { gender?: TiebaUserGenderQueryParam };

export type Cursor = string;
export type JsonString = string;
interface CursorPagination {
    currentCursor: Cursor,
    nextCursor: Cursor,
    hasMore: boolean
}
export interface ApiPosts {
    type: 'index' | 'search',
    pages: CursorPagination & {
        matchQueryPostCount: { [P in PostType]: UInt },
        notMatchQueryParentPostCount: { [P in Omit<PostType, 'subRely'>]: UInt }
    },
    forum: Pick<ApiForumList[number], 'fid' | 'name'>,
    threads: Array<Thread & {
        replies: Array<Reply & {
            subReplies: SubReply[]
        }>
    }>,
    users: TiebaUser[]
}
export interface ApiPostsParam extends ApiQueryParamCursorPagination { query: JsonString }
