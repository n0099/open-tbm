import type { LocationAsRelativeRaw } from 'vue-router';
import _ from 'lodash';

export const toUserProfileUrl = (user: Partial<Pick<User, 'name' | 'portrait'>>) =>
    (_.isEmpty(user.portrait)
        ? `https://tieba.baidu.com/home/main?un=${user.name}`
        : `https://tieba.baidu.com/home/main?id=${user.portrait}`);
export const toUserPortraitImageUrl = (portrait: string) =>
    `https://himg.bdimg.com/sys/portrait/item/${portrait}.jpg`; // use /sys/portraith for high-res image
export const toUserRoute = (uid: BaiduUserID): LocationAsRelativeRaw =>
    ({ name: 'users/uid', params: { uid: uid.toString() } });

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
export const baseRenderUsername = (getUser: ReturnType<typeof baseGetUser>) => (uid: BaiduUserID) => {
    const { name, displayName } = getUser(uid);
    if (name === null)
        return displayName ?? `无用户名或覆盖名 百度UID=${uid}`;

    return name + (displayName === null ? '' : ` ${displayName}`);
};
export interface UserProvision {
    getUser: ReturnType<typeof baseGetUser>,
    renderUsername: ReturnType<typeof baseRenderUsername>
}
export const useUserProvision = () => {
    const users = ref<User[]>([]);
    const getUser = computed(() => baseGetUser(users.value));
    const renderUsername = computed(() => baseRenderUsername(getUser.value));
    const userProvision = { getUser, renderUsername };
    const provideUsers = (inject: User[]) => {
        users.value = inject;
        provide<typeof userProvision>('userProvision', userProvision);
    };
    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
    const injectUsers = () => inject<typeof userProvision>('userProvision')!;

    return { provide: provideUsers, inject: injectUsers };
};
