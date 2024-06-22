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

export type Cursor = string;
interface CursorPagination {
    pages: {
        currentCursor: Cursor,
        nextCursor: Cursor | null
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
