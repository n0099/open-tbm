<template>
    <div class="row align-items-center">
        <template v-if="isLoading">
            <div class="col"><hr /></div>
            <div class="w-auto">
                <div class="loading mx-auto" />
            </div>
        </template>
        <div class="col"><hr /></div>
        <div class="w-100" />
        <div class="col">
            <div class="tombstone-post-list" />
        </div>
    </div>
</template>

<script>
export default {
    props: {
        isLoading: { type: Boolean, default: true }
    }
};
</script>

<style scoped>
.loading {
    width: 120px;
    height: 120px;
}

.tombstone-post-list {
    height: 480px;
    background-image: url('../../public/assets/tombstone-post-list.svg');
    background-size: 100%;
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
