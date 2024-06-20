<template>
    <template v-if="error instanceof FetchResponseError">
        <pre class="text-muted">{{ error.responseBody }}</pre>
    </template>
    <template v-else-if="error instanceof ApiResponseError">
        <div class="text-center">
            <p class="error-code text-muted">{{ error.errorCode }}</p>
            <template v-if="_.isString(error.errorInfo)">
                <p v-for="(info, _k) in error.errorInfo.split('\n')" :key="_k">{{ info }}</p>
            </template>
            <template v-else-if="_.isObject(error.errorInfo)">
                <p v-for="(paramError, paramName) in error.errorInfo" :key="paramName">
                    参数 {{ paramName }}：
                    <template v-if="_.isString(paramError)">
                        <p v-for="(line, _k) in paramError.split('\n')" :key="_k">{{ line }}</p>
                    </template>
                    <template v-else-if="_.isArray(paramError) && paramError.length > 1">
                        <p v-for="(item, _k) in paramError" :key="_k">{{ item }}</p>
                    </template>
                    <template v-else>{{ paramError }}</template>
                </p>
            </template>
        </div>
    </template>
</template>

<script setup lang="ts">
import type { ApiErrorClass } from '@/api';
import { ApiResponseError, FetchResponseError } from '@/api';
import _ from 'lodash';

defineProps<{ error: ApiErrorClass | null }>();
</script>

<style scoped>
.error-code {
    font-size: 6rem;
}
</style>
