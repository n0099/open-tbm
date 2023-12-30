import type { BaiduUserID } from '@/api/user';
import type { ObjValues } from '@/shared';

export const selectTiebaUserBy = ['', 'uid', 'name', 'nameNULL', 'displayName', 'displayNameNULL'] as const;
export type SelectTiebaUserBy = typeof selectTiebaUserBy[number];
export type SelectTiebaUserParams = Partial<{
    uid: BaiduUserID,
    uidCompareBy: '<' | '=' | '>',
    // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
    name: string | 'NULL',
    nameUseRegex: boolean,
    // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
    displayName: string | 'NULL',
    displayNameUseRegex: boolean
}>;
type SelectTiebaUserParamsValue = ObjValues<SelectTiebaUserParams>;
export const selectTiebaUserParamsName = [
    'uid', 'uidCompareBy', 'name', 'nameUseRegex', 'displayName', 'displayNameUseRegex'
] as const;

// widen type Record<string, SelectTiebaUserParamsValue> for compatible with props.paramsNameMap
export interface SelectTiebaUserModel {
    selectBy: SelectTiebaUserBy,
    params: Record<string, SelectTiebaUserParamsValue> | SelectTiebaUserParams
}
