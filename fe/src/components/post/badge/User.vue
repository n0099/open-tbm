<template>
<div class="btn-group" role="group">
    <button
        v-if="uid === threadAuthorUid"
        type="button" class="badge btn btn-success">
        楼主
    </button>
    <button
        v-if="uid === replyAuthorUid"
        type="button" class="badge btn btn-info">
        层主
    </button>
    <template v-if="forumModerator !== null && forumModerator.moderatorTypes !== ''">
        <button
            v-for="[moderator, bootstrapColor] in Object.values(moderators)"
            :key="moderator" type="button"
            :class="`badge btn btn-${bootstrapColor}`">
            {{ moderator }}
        </button>
        <button v-if="_.isEmpty(moderators)" type="button" class="badge btn btn-info">
            {{ forumModerator.moderatorTypes }}
        </button>
    </template>
    <button
        v-if="authorExpGrade !== null"
        type="button" class="badge btn btn-primary">
        {{ authorExpGrade.authorExpGrade }}级
    </button>
</div>
</template>

<script setup lang="ts">
import _ from 'lodash';

const { user } = defineProps<{
    user: User,
    threadAuthorUid?: BaiduUserID,
    replyAuthorUid?: BaiduUserID
}>();
const { uid, forumSpecific: { authorExpGrade, forumModerator } } = user;
const moderators = computed(() => _.pick(knownModeratorTypes,
    forumModerator?.moderatorTypes.split(',') ?? []));
</script>
