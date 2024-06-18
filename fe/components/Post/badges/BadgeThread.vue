<template>
    <div class="btn-group align-baseline" role="group">
        <button v-if="thread.stickyType === 'membertop'" class="badge btn btn-warning" type="button">会员置顶</button>
        <button v-if="thread.stickyType === 'top'" class="badge btn btn-primary" type="button">置顶</button>
        <button v-if="thread.isGood" class="badge btn btn-danger" type="button">精品</button>
        <button v-if="thread.topicType === 'text'" class="badge btn btn-primary" type="button">文本话题</button>
        <!-- TODO: fill unknown picture topic thread type -->
        <button v-if="thread.topicType === ''" class="badge btn btn-primary" type="button">图片话题</button>
    </div>
</template>

<script setup lang="ts">
import type { Thread } from '~/api/post';

defineProps<{ thread: Thread }>();
</script>
