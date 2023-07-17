import type { BaiduUserID } from './user';
import type { BoolInt, Int, ObjUnknown, Pid, Spid, Tid, UInt, UnixTimestamp } from '@/shared';

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

export interface Thread extends Post {
    threadType: UInt | 1024 | 1040 | null,
    stickyType: 'membertop' | 'top' | null,
    topicType: '' | 'text' | null,
    isGood: BoolInt,
    title: string,
    latestReplyPostedAt: UnixTimestamp,
    latestReplierUid: BaiduUserID | null,
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
    content: string, // original json convert to HTML string via be/app/resources/views/renderPostContent.blade.php
    subReplyCount: UInt,
    isFold: UInt | 0 | 6,
    geolocation: ObjUnknown | null
}
export interface SubReply extends Post {
    pid: Pid,
    spid: Spid,
    content: string // original json convert to HTML string via be/app/resources/views/renderPostContent.blade.php
}
