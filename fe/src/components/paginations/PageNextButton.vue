<template>
    <div class="row align-items-center p-4">
        <div class="col align-middle"><hr /></div>
        <div class="w-auto">
            <button @click="() => $emit('click')" class="btn btn-secondary fs-5">下一页</button>
        </div>
        <div class="col align-middle"><hr /></div>
    </div>
</template>

<script setup lang="ts">
defineEmits<{ click: [] }>();
</script>
