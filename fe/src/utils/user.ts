import type { LocationAsRelativeRaw } from 'vue-router';
import _ from 'lodash';

export const toUserProfileUrl = (user: Partial<Pick<User, 'name' | 'portrait'>>) =>
    (_.isEmpty(user.portrait)
        ? `https://tieba.baidu.com/home/main?un=${user.name}`
        : `https://tieba.baidu.com/home/main?id=${user.portrait}`);
export const toUserPortraitImageUrl = (portrait: string) =>
    `https://himg.bdimg.com/sys/portrait/item/${portrait}.jpg`; // use /sys/portraith for high-res image
export const toUserRoute = (uid: BaiduUserID): LocationAsRelativeRaw =>
    ({ name: 'users/uid', params: { uid } });

export const baseGetUser = (users: User[]) => {
    const usersKeyByUid = _.mapKeys(users, 'uid');

    return (uid: BaiduUserID): User => (usersKeyByUid[uid] as User | undefined) ?? {
        uid: 0,
        name: '未知用户',
        displayName: null,
        portrait: '',
        portraitUpdatedAt: null,
        gender: 0,
        fansNickname: null,
        icon: [],
        ipGeolocation: null,
        createdAt: 0,
        updatedAt: 0,
        currentForumModerator: null,
        currentAuthorExpGrade: null
    };
};
export const baseGetLatestReplier = (latestRepliers: LatestReplier[]) => {
    const latestRepliersKeyById = _.mapKeys(latestRepliers, 'id');

    return (id: LatestReplierId | null): LatestReplier | undefined =>
        (id === null ? undefined : latestRepliersKeyById[id]);
};
export const baseRenderUsername = (getUser: ReturnType<typeof baseGetUser>) => (uid: BaiduUserID) => {
    const { name, displayName } = getUser(uid);
    if (name === null)
        return displayName ?? `无用户名或覆盖名 百度UID=${uid}`;

    return name + (displayName === null ? '' : ` ${displayName}`);
};
