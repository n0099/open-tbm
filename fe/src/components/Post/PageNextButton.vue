<template>
    <div class="p-3">
        <div class="row align-items-center">
            <div class="col"><hr /></div>
            <div class="w-auto">
                <button @click="$emit('load-page', currentPage + 1)" class="btn btn-secondary" type="button">
                    <span class="h4">下一页</span>
                </button>
            </div>
            <div class="col"><hr /></div>
        </div>
    </div>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

    }
});

const postPageNextButtonComponent = Vue.component('post-page-next', {
    template: '#post-page-next-template',
    props: {
        currentPage: { type: Number, required: true }
    }
});
</script>

<style scoped>

</style>
