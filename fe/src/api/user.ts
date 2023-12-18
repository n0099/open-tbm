import type { TimestampFields } from './posts';
import type { Int, ObjUnknown, UInt, UnixTimestamp } from '@/shared';

export type BaiduUserID = Int;
export type ForumModeratorType = 'assist'
| 'fourth_manager'
| 'fourthmanager'
| 'manager'
| 'picadmin'
| 'publication_editor'
| 'publication'
| 'videoadmin'
| 'voiceadmin';
export type AuthorExpGrade = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 | 16 | 17 | 18;
export type TiebaUserGender = 0 | 1 | 2 | null;
export type TiebaUserGenderQueryParam = '0' | '1' | '2' | 'NULL';

export interface TiebaUser extends TimestampFields {
    uid: BaiduUserID,
    name: string | null,
    displayName: string | null,
    portrait: string,
    portraitUpdatedAt: UInt | null,
    gender: TiebaUserGender,
    fansNickname: string | null,
    icon: ObjUnknown[] | null,
    ipGeolocation: string | null,
    currentForumModerator: {
        discoveredAt: UnixTimestamp,
        moderatorTypes: ForumModeratorType | '' | `${ForumModeratorType},${ForumModeratorType}`
    } | null,
    currentAuthorExpGrade: {
        discoveredAt: UnixTimestamp,
        authorExpGrade: AuthorExpGrade
    } | null
}
