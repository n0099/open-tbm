export interface TimestampFields {
    createdAt: UnixTimestamp,
    updatedAt: UnixTimestamp | null
}
interface Post extends Agree, TimestampFields {
    tid: Tid,
    authorUid: BaiduUserID,
    postedAt: UnixTimestamp,
    lastSeenAt: UnixTimestamp | null
}
interface Agree {
    agreeCount: Int,
    disagreeCount: Int
}

export type LatestReplierId = UInt;
export type LatestReplier = TimestampFields
    & { id: LatestReplierId }
    & (Pick<User, 'uid'> | ({ uid: null } & Pick<User, 'name' | 'displayName'>));

export interface Thread extends Post {
    // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
    threadType: UInt | 1024 | 1040 | null,
    stickyType: 'membertop' | 'top' | null,
    topicType: '' | 'text' | null,
    isGood: BoolInt,
    title: string,
    latestReplyPostedAt: UnixTimestamp,
    latestReplierId: LatestReplierId | null,
    replyCount: UInt,
    viewCount: UInt,
    shareCount: UInt,
    zan: ObjUnknown | null,
    geolocation: ObjUnknown | null,
    authorPhoneType: string
}
export interface Reply extends Post {
    pid: Pid,
    floor: UInt,
    content: PostContent | null,
    subReplyCount: UInt,
    // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
    isFold: UInt | 0 | 6,
    geolocation: ObjUnknown | null
}
export interface SubReply extends Post {
    pid: Pid,
    spid: Spid,
    content: PostContent | null
}
