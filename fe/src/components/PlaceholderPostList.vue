<template>
    <div class="row align-items-center">
        <template v-if="isLoading">
            <div class="col"><hr /></div>
            <div class="w-auto">
                <picture>
                    <source srcset="../../public/assets/icon-huaji-loading-spinner.avifs" type="image/avif" />
                    <img src="../../public/assets/icon-huaji-loading-spinner-2nd-frames-removed.gif" />
                </picture>
            </div>
        </template>
        <div class="col"><hr /></div>
        <div class="w-100" />
        <div class="col">
            <img :class="{ animate: isLoading }" src="../../public/assets/tombstone-post-list.svg" />
        </div>
    </div>
</template>

<script lang="ts">
export default {
    props: {
        isLoading: { type: Boolean, default: true }
    }
};
</script>

<style scoped>
.animate {
    animation-name: tombstone-post-list;
    animation-play-state: running;
    animation-iteration-count: infinite;
    animation-duration: 1.5s;
}
@keyframes tombstone-post-list {
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
