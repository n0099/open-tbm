<template>
<img
    :src="toUserPortraitImageUrl(user.portrait)"
    :alt="alt" loading="lazy" :class="{
        'tieba-user-portrait-small': size === 'small',
        'tieba-user-portrait-large': size === 'large'
    }" />
</template>

<script setup lang="ts">
const { user } = defineProps<{
    user: User,
    size: 'small' | 'large'
}>();
const alt = computed(() =>
    `百度用户UID${user.uid} ${user.name} ${user.displayName} 的头像`);
</script>

<style scoped>
img {
    object-fit: contain;
}
.tieba-user-portrait-small {
    inline-size: 1.6rem;
    block-size: 1.6rem;
}
.tieba-user-portrait-large {
    inline-size: 6rem;
    block-size: 6rem;
}
</style>
