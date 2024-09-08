<template>
<NuxtLayout>
    <div class="container">
        <PlaceholderError
            :error="new ApiResponseError(
                error.statusCode,
                _.isEmpty(error.statusMessage) || error.statusMessage === undefined ? error.message : error.statusMessage
            )" />
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div v-if="'stack' in error" v-html="error.stack" />
        <pre v-if="'toJSON' in error">{{ JSON.stringify(error.toJSON()) }}</pre>
    </div>
</NuxtLayout>
</template>

<script setup lang="ts">
import type { NuxtError } from '#app';
import _ from 'lodash';

defineProps<{ error: NuxtError }>();
</script>
