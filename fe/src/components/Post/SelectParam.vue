<template>
    <select @change="$emit('param-change', $event)" class="col-2 custom-select form-control">
        <option selected value="add" disabled>New...</option>
        <optgroup v-for="(group, groupName) in paramsGroup" :label="groupName">
            <option v-for="(paramDescription, paramName) in group" :selected="currentParam === paramName" :value="paramName">{{ paramDescription }}</option>
        </optgroup>
    </select>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

    }
});

const selectParamComponent = Vue.component('select-param', {
    template: '#select-param-template',
    props: {
        currentParam: String
    },
    data () {
        return {
            paramsGroup: {
                贴子ID: {
                    tid: 'tid（主题贴ID）',
                    pid: 'pid（回复贴ID）',
                    spid: 'spid（楼中楼ID）'
                },
                所有贴子类型: {
                    postTime: '发帖时间',
                    authorUid: '发帖人UID',
                    authorName: '发帖人用户名',
                    authorDisplayName: '发帖人覆盖名',
                    authorGender: '发帖人性别',
                    authorManagerType: '发帖人吧务级别'
                },
                仅主题贴: {
                    latestReplyTime: '最后回复时间',
                    threadTitle: '主题贴标题',
                    threadViewNum: '主题贴浏览量',
                    threadShareNum: '主题贴分享量',
                    threadReplyNum: '主题贴回复量',
                    threadProperties: '主题贴属性',
                    latestReplierUid: '最后回复人UID',
                    latestReplierName: '最后回复人用户名',
                    latestReplierDisplayName: '最后回复人覆盖名',
                    latestReplierGender: '最后回复人性别'
                },
                仅回复贴: {
                    replySubReplyNum: '楼中楼回复量'
                },
                仅回复贴或楼中楼: {
                    postContent: '贴子内容',
                    authorExpGrade: '发帖人经验等级'
                }
            }
        };
    }
});
</script>

<style scoped>
.select-param.is-invalid {
    z-index: 1; /* let border overlaps other selects */
}
.select-param-first-row {
    border-top-left-radius: 0.25em !important;
}
.select-param-last-row {
    border-bottom-left-radius: 0.25em !important;
}
</style>
