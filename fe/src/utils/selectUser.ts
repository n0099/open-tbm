export const selectUserBy = ['', 'uid', 'name', 'nameNULL', 'displayName', 'displayNameNULL'] as const;
export type SelectUserBy = typeof selectUserBy[number];
export type SelectUserParams = Partial<{
    uid: BaiduUserID,
    uidCompareBy: '<' | '=' | '>',
    // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
    name: string | 'NULL',
    nameUseRegex: boolean,
    // eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
    displayName: string | 'NULL',
    displayNameUseRegex: boolean
}>;
type SelectUserParamsValue = ObjValues<SelectUserParams>;
export const selectUserParamsName = [
    'uid', 'uidCompareBy', 'name', 'nameUseRegex', 'displayName', 'displayNameUseRegex'
] as const;

// widen type Record<string, SelectUserParamsValue> for compatible with props.paramsNameMap
export interface SelectUserModel {
    selectBy: SelectUserBy,
    params: Record<string, SelectUserParamsValue> | SelectUserParams
}
