<template>
    <div class="text-center">
        <hr />
        <template v-if="error === null">
            <span class="errorCode text-muted">error</span>
        </template>
        <template v-else>
            <p class="errorCode text-muted">{{ error.errorCode }}</p>
            <p v-for="(info, key) in error.errorInfo.split('\n')" :key="key">{{ info }}</p>
        </template>
    </div>
</template>

<script lang="ts">
import type { ApiError } from '@/api/index.d';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

export default defineComponent({
    props: {
        error: { type: [Object, null] as PropType<ApiError | null>, required: true }
    }
});
</script>

<style scoped>
.errorCode {
    font-size: 6rem;
}
</style>
