<template>
    <div class="btn-group" role="group">
        <button v-if="user.uid === threadAuthorUid"
                type="button" class="badge btn btn-success">楼主</button>
        <button v-if="user.uid === replyAuthorUid"
                type="button" class="badge btn btn-info">层主</button>
        <template v-if="user.currentForumModerator !== null">
            <button v-if="user.currentForumModerator.moderatorType === 'manager'"
                    type="button" class="badge btn btn-danger">吧主</button>
            <button v-else-if="user.currentForumModerator.moderatorType === 'assist'"
                    type="button" class="badge btn btn-info">小吧</button>
            <button v-else-if="user.currentForumModerator.moderatorType === 'voiceadmin'"
                    type="button" class="badge btn btn-info">语音小编</button>
        </template>
        <button v-if="user.expGrade !== undefined"
                type="button" class="badge btn btn-primary">Lv{{ user.expGrade }}</button>
    </div>
</template>

<script setup lang="ts">
import type { BaiduUserID, TiebaUserRecord } from '@/api/index.d';

defineProps<{
    user: TiebaUserRecord,
    threadAuthorUid: BaiduUserID,
    replyAuthorUid?: BaiduUserID
}>();
</script>
