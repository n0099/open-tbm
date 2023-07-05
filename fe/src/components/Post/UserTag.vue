<template>
    <div class="btn-group" role="group">
        <button v-if="user.uid === threadAuthorUid"
                type="button" class="badge btn btn-success">楼主</button>
        <button v-if="user.uid === replyAuthorUid"
                type="button" class="badge btn btn-info">层主</button>
        <template v-if="user.currentForumModerator !== null && user.currentForumModerator.moderatorTypes !== ''">
            <button v-for="moderator in Object.values(moderators)"
                    :key="moderator[0]" type="button"
                    :class="`badge btn btn-${moderator[1]}`">{{ moderator[0] }}</button>
            <button v-if="_.isEmpty(moderators)" type="button" class="badge btn btn-info">
                {{ user.currentForumModerator.moderatorTypes }}
            </button>
        </template>
        <button v-if="user.expGrade !== undefined"
                type="button" class="badge btn btn-primary">Lv{{ user.expGrade }}</button>
    </div>
</template>

<script setup lang="ts">
import type { BaiduUserID, ForumModeratorType, TiebaUserRecord } from '@/api/index.d';
import type { BootstrapColors } from '@/shared';
import { computed } from 'vue';
import _ from 'lodash';

const props = defineProps<{
    user: TiebaUserRecord,
    threadAuthorUid?: BaiduUserID,
    replyAuthorUid?: BaiduUserID
}>();

const knownModeratorTypes: { [P in ForumModeratorType]: [string, BootstrapColors] } = {
    // eslint-disable-next-line @typescript-eslint/naming-convention
    fourth_manager: ['第四吧主', 'danger'],
    fourthmanager: ['第四吧主', 'danger'],
    manager: ['吧主', 'danger'],
    assist: ['小吧', 'primary'],
    picadmin: ['图片小编', 'warning'],
    videoadmin: ['视频小编', 'warning'],
    voiceadmin: ['语音小编', 'secondary'],
    // eslint-disable-next-line @typescript-eslint/naming-convention
    publication_editor: ['吧刊小编', 'secondary'],
    publication: ['吧刊小编', 'secondary']
};
const moderators = computed(() =>
    _.pick(knownModeratorTypes, props.user.currentForumModerator?.moderatorTypes.split(',') ?? []));
</script>
