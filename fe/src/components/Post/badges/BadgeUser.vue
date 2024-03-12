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
        <button v-if="user.currentAuthorExpGrade !== null"
                type="button" class="badge btn btn-primary">{{ user.currentAuthorExpGrade.authorExpGrade }}级</button>
    </div>
</template>

<script setup lang="ts">
import type { BaiduUserID, ForumModeratorType, User } from '@/api/user';
import type { BootstrapColor } from '@/shared';
import { computed } from 'vue';
import * as _ from 'lodash-es';

const props = defineProps<{
    user: User,
    threadAuthorUid?: BaiduUserID,
    replyAuthorUid?: BaiduUserID
}>();

const knownModeratorTypes: { [P in ForumModeratorType]: [string, BootstrapColor] } = {
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
const moderators = computed(() => _.pick(knownModeratorTypes,
    props.user.currentForumModerator?.moderatorTypes.split(',') ?? []));
</script>
