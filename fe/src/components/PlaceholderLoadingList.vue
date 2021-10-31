<template>
    <div class="row align-items-center">
        <div class="col"><hr /></div>
        <div class="w-auto">
            <div class="loading mx-auto"></div>
        </div>
        <div class="col"><hr /></div>
        <div class="w-100"></div>
        <div class="col">
            <div class="tombstone-post-list"></div>
        </div>
    </div>
</template>

<script>
export default {};
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
}
</style>
