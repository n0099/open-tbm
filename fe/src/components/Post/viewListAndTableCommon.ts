import type { BaiduUserID, TiebaUserRecord } from '@/api/index.d';
import './tiebaPostElements.css';
import 'viewerjs/dist/viewer.css';
import viewer from 'v-viewer';
import { app } from '@/main';
import _ from 'lodash';

app.use(viewer, {
    defaultOptions: {
        url: 'data-origin',
        filter: (img: HTMLImageElement) => img.classList.contains('tieba-image')
    }
});

export const baseGetUser = (users: TiebaUserRecord[]) => (uid: BaiduUserID): TiebaUserRecord => _.find(users, { uid }) ?? {
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
    currentForumModerator: null
};
export const baseRenderUsername = (injectedGetUser: ReturnType<typeof baseGetUser>) => (uid: BaiduUserID) => {
    const { name, displayName } = injectedGetUser(uid);
    if (name === null) return displayName ?? `无用户名或覆盖名（UID：${uid}）`;
    return name + (displayName === null ? '' : `（${displayName}）`);
};
