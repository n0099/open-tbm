import type { Reply, SubReply, Thread } from './post';
import type { User, UserGenderQueryParam } from './user';
import type { SelectUserParams } from '@/components/widgets/selectUser';
import type { BoolInt, Fid, Float, PostType, UInt, UnixTimestamp } from '@/shared';
import type { Mix } from '@/shared/groupBytimeGranularityUtcPlus8';

export interface ApiError { errorCode: number, errorInfo: Record<string, unknown[]> | string }
export interface Api<TResponse, TQueryParam = never> {
    response: TResponse,
    queryParam: TQueryParam
}

export type ApiForums = Api<Array<{
    id: UInt,
    fid: Fid,
    name: string,
    isCrawling: BoolInt
}>>;

export type ApiStatus = Api<Array<{
    startTime: UnixTimestamp,
    queueTiming: Float,
    webRequestTiming: Float,
    savePostsTiming: Float,
    webRequestTimes: UInt,
    parsedPostTimes: UInt,
    parsedUserTimes: UInt
}>, {
        timeGranularity: 'day' | 'hour' | 'minute',
        startTime: UnixTimestamp,
        endTime: UnixTimestamp
    }>;

interface TimeCountPair { time: Mix, count: UInt }
export type ApiStatsForumPostCount = Api<{
    thread: TimeCountPair[],
    reply: TimeCountPair[],
    subReply: TimeCountPair[]
}, {
        fid: Fid,
        timeGranularity: 'day' | 'hour' | 'minute' | 'month' | 'week' | 'year',
        startTime: UnixTimestamp,
        endTime: UnixTimestamp
    }>;

export type Cursor = string;
interface CursorPagination {
    pages: {
        currentCursor: Cursor,
        nextCursor: Cursor,
        hasMore: boolean
    }
}

export type ApiUsers = Api<
    CursorPagination & { users: User[] },
    SelectUserParams & { gender?: UserGenderQueryParam }
>;

export type JsonString = string;
export type ApiPosts = Api<CursorPagination & {
    pages: {
        matchQueryPostCount: { [P in PostType]: UInt },
        notMatchQueryParentPostCount: { [P in Omit<PostType, 'subRely'>]: UInt }
    },
    type: 'index' | 'search',
    forum: Pick<ApiForums[number], 'fid' | 'name'>,
    threads: Array<Thread & {
        replies: Array<Reply & {
            subReplies: SubReply[]
        }>
    }>,
    users: User[]
}, { query: JsonString }>;
