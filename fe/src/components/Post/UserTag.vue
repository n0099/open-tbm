<template>
    <div class="btn-group" role="group">
        <template v-if="user.uid !== undefined">
            <button v-if="user.uid.current === user.uid.thread"
                    type="button" class="badge btn btn-success">楼主</button>
            <button v-else-if="user.uid.current === user.uid.reply"
                    type="button" class="badge btn btn-info">层主</button>
        </template>
        <button v-if="user.managerType === 'manager'" type="button" class="badge btn btn-danger">吧主</button>
        <button v-else-if="user.managerType === 'assist'" type="button" class="badge btn btn-info">小吧</button>
        <button v-else-if="user.managerType === 'voiceadmin'" type="button" class="badge btn btn-info">语音小编</button>
        <button v-if="user.expGrade !== undefined" type="button" class="badge btn btn-primary">Lv{{ user.expGrade }}</button>
    </div>
</template>

<script setup lang="ts">
import type { AuthorExpGrade, AuthorManagerType, BaiduUserID } from '@/api/index.d';

defineProps<{ user: {
    uid?: { current: BaiduUserID } & ({ reply: BaiduUserID } | { thread: BaiduUserID }),
    managerType: AuthorManagerType,
    expGrade?: AuthorExpGrade
} }>();
</script>
