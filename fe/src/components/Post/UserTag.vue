<template>
    <div class="btn-group" role="group">
        <template v-if="userInfo.uid !== undefined">
            <button v-if="userInfo.uid.current === userInfo.uid.thread"
                    type="button" class="badge btn btn-success">楼主</button>
            <button v-else-if="userInfo.uid.current === userInfo.uid.reply"
                    type="button" class="badge btn btn-info">层主</button>
        </template>
        <button v-if="userInfo.managerType === 'manager'" type="button" class="badge btn btn-danger">吧主</button>
        <button v-else-if="userInfo.managerType === 'assist'" type="button" class="badge btn btn-info">小吧</button>
        <button v-else-if="userInfo.managerType === 'voiceadmin'" type="button" class="badge btn btn-info">语音小编</button>
        <button v-if="userInfo.expGrade !== undefined" type="button" class="badge btn btn-primary">Lv{{ userInfo.expGrade }}</button>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

export default defineComponent({
    props: {
        userInfo: { type: Object, required: true }
    }
});
</script>
