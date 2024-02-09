<template>
    <div class="text-center">
        <template v-if="error === null">
            <span class="errorCode text-muted">error</span>
        </template>
        <template v-else>
            <p class="errorCode text-muted">{{ error.errorCode }}</p>
            <template v-if="_.isString(error.errorInfo)">
                <p v-for="(info, _k) in error.errorInfo.split('\n')" :key="_k">{{ info }}</p>
            </template>
            <template v-else-if="_.isObject(error.errorInfo)">
                <p v-for="(lines, paramName) in error.errorInfo" :key="paramName">
                    参数 {{ paramName }}：
                    <template v-if="_.isString(lines)">
                        <p v-for="(info, _k) in lines.split('\n')" :key="_k">{{ info }}</p>
                    </template>
                    <template v-else>{{ lines }}</template>
                </p>
            </template>
        </template>
    </div>
</template>

<script setup lang="ts">
import type { ApiError } from '@/api/index.d';
import * as _ from 'lodash-es';

defineProps<{ error: ApiError | null }>();
</script>

<style scoped>
.errorCode {
    font-size: 6rem;
}
</style>
