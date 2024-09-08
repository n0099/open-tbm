<template>
<div class="row align-items-center">
    <template v-if="isLoading">
        <div class="col"><hr /></div>
        <div class="w-auto">
            <picture>
                <source :srcset="iconHuajiLoadingSpinnerAvifs" type="image/avif" />
                <img :src="iconHuajiLoadingSpinnerGif" alt="loading" />
            </picture>
        </div>
    </template>
    <div class="col"><hr /></div>
    <div class="w-100" />
    <div class="col">
        <img :class="{ animate: isLoading }" :src="placeholderPostList" alt="placeholder" />
    </div>
</div>
</template>

<script setup lang="ts">
import iconHuajiLoadingSpinnerGif from '@/assets/icon-huaji-loading-spinner-2nd-frames-removed.gif';
import iconHuajiLoadingSpinnerAvifs from '@/assets/icon-huaji-loading-spinner.avifs';
import placeholderPostList from '@/assets/placeholder-post-list.svg';

defineProps<{ isLoading: boolean }>();
</script>

<style scoped>
.animate {
    animation-name: placeholder-post-list;
    animation-play-state: running;
    animation-iteration-count: infinite;
    animation-duration: 1.5s;
}
@keyframes placeholder-post-list {
    0% {
        opacity: .5;
    }
    50% {
        opacity: 1;
    }
    100% {
        opacity: .5;
    }
}
</style>
