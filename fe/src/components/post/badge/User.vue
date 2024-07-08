<template>
<div class="btn-group" role="group">
    <button
        v-if="user.uid === threadAuthorUid"
        type="button" class="badge btn btn-success">
        楼主
    </button>
    <button
        v-if="user.uid === replyAuthorUid"
        type="button" class="badge btn btn-info">
        层主
    </button>
    <template v-if="user.currentForumModerator !== null && user.currentForumModerator.moderatorTypes !== ''">
        <button
            v-for="moderator in Object.values(moderators)"
            :key="moderator[0]" type="button"
            :class="`badge btn btn-${moderator[1]}`">
            {{ moderator[0] }}
        </button>
        <button v-if="_.isEmpty(moderators)" type="button" class="badge btn btn-info">
            {{ user.currentForumModerator.moderatorTypes }}
        </button>
    </template>
    <button
        v-if="user.currentAuthorExpGrade !== null"
        type="button" class="badge btn btn-primary">
        {{ user.currentAuthorExpGrade.authorExpGrade }}级
    </button>
</div>
</template>

<script setup lang="ts">
import _ from 'lodash';

const props = defineProps<{
    user: User,
    threadAuthorUid?: BaiduUserID,
    replyAuthorUid?: BaiduUserID
}>();

const knownModeratorTypes: { [P in ForumModeratorType]: [string, BootstrapColor] } = {
    ...keysWithSameValue(['fourth_manager', 'fourthmanager'], ['第四吧主', 'danger']),
    manager: ['吧主', 'danger'],
    assist: ['小吧', 'primary'],
    picadmin: ['图片小编', 'warning'],
    videoadmin: ['视频小编', 'warning'],
    voiceadmin: ['语音小编', 'secondary'],
    ...keysWithSameValue(['publication_editor', 'publication'], ['吧刊小编', 'secondary'])
};
const moderators = computed(() => _.pick(knownModeratorTypes,
    props.user.currentForumModerator?.moderatorTypes.split(',') ?? []));
</script>
