<template>
<div class="text-center">
    <template v-if="error instanceof FetchError">
        <p class="error-code text-muted">{{ error.statusCode }}</p>
        <pre class="text-muted">{{ error.statusMessage }}</pre>
        <p class="text-muted font-monospace">{{ error.message }}</p>
    </template>
    <template v-else-if="error instanceof ApiResponseError">
        <p class="error-code text-muted">{{ error.errorCode }}</p>
        <template v-if="_.isString(error.errorInfo)">
            <p v-for="(info, _k) in error.errorInfo.split('\n')" :key="_k">{{ info }}</p>
        </template>
        <template v-else-if="_.isObject(error.errorInfo)">
            <pre class="text-start">{{ JSON.stringify(error.errorInfo, null, 4) }}</pre>
        </template>
    </template>
</div>
</template>

<script setup lang="ts">
import { FetchError } from 'ofetch';
import _ from 'lodash';

const { error } = defineProps<{ error: ApiErrorClass | null }>();
responseWithError(error);
</script>

<style scoped>
.error-code {
    font-size: 6rem;
}
</style>
