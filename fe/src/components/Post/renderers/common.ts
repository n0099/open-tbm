import type { BaiduUserID, User } from '@/api/user';
import { app } from '@/main';
import '@/styles/tieba.css';
import viewer from 'v-viewer';
import 'viewerjs/dist/viewer.css';
import * as _ from 'lodash-es';

app.use(viewer, {
    defaultOptions: {
        url: 'data-origin',
        filter: (img: HTMLImageElement) => img.classList.contains('tieba-image')
    }
});

export const baseGetUser = (users: User[]) => (uid: BaiduUserID): User => _.find(users, { uid }) ?? {
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
export const baseRenderUsername = (injectedGetUser: ReturnType<typeof baseGetUser>) => (uid: BaiduUserID) => {
    const { name, displayName } = injectedGetUser(uid);
    if (name === null)
        return displayName ?? `无用户名或覆盖名 百度UID=${uid}`;

    return name + (displayName === null ? '' : ` ${displayName}`);
};
