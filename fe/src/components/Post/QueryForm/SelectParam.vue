<template>
    <select v-model="selected" @change="$emit('paramChange', $event.target.value)"
            class="form-select form-control flex-grow-0">
        <option value="add" disabled>New...</option>
        <optgroup v-for="(group, groupName) in paramsGroup" :key="groupName" :label="groupName">
            <option v-for="(paramDescription, paramName) in group"
                    :key="paramName" :value="paramName">{{ paramDescription }}</option>
        </optgroup>
    </select>
</template>

<script lang="ts">
import { emitEventStrValidator } from '@/shared';
import { defineComponent, ref, watchEffect } from 'vue';

export default defineComponent({
    props: {
        currentParam: { type: String, required: true }
    },
    emits: {
        paramChange: emitEventStrValidator
    },
    setup(props) {
        const paramsGroup = {
            帖子ID: {
                tid: 'tid（主题帖ID）',
                pid: 'pid（回复帖ID）',
                spid: 'spid（楼中楼ID）'
            },
            所有帖子类型: {
                postTime: '发帖时间',
                authorUid: '发帖人UID',
                authorName: '发帖人用户名',
                authorDisplayName: '发帖人覆盖名',
                authorGender: '发帖人性别',
                authorManagerType: '发帖人吧务级别'
            },
            仅主题帖: {
                latestReplyTime: '最后回复时间',
                threadTitle: '主题帖标题',
                threadViewNum: '主题帖浏览量',
                threadShareNum: '主题帖分享量',
                threadReplyNum: '主题帖回复量',
                threadProperties: '主题帖属性',
                latestReplierUid: '最后回复人UID',
                latestReplierName: '最后回复人用户名',
                latestReplierDisplayName: '最后回复人覆盖名',
                latestReplierGender: '最后回复人性别'
            },
            仅回复帖: {
                replySubReplyNum: '楼中楼回复量'
            },
            仅回复帖或楼中楼: {
                postContent: '帖子内容',
                authorExpGrade: '发帖人经验等级'
            }
        };

        const selected = ref('add');
        watchEffect(() => {
            selected.value = props.currentParam;
        });

        return { selected, paramsGroup };
    }
});
</script>

<style scoped>
select {
    width: 20% !important;
}
</style>
