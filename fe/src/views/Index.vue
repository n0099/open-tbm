<template>
    <div class="text-center" style="font-size: 8rem">WIP</div>
    <div class="text-center" style="font-size: 2rem">开发中</div>
    <div class="text-center" style="font-size: 2rem">请访问其他页面</div>
</template>

<script setup>
import { titleTemplate } from '@/shared';
import { useHead } from '@vueuse/head';

useHead({ title: titleTemplate('首页') });
</script>
