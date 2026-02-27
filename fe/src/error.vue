<template>
<NuxtLayout>
    <div class="container">
        <PlaceholderError
            :error="new ApiResponseError(
                error.status,
                _.isEmpty(error.statusText) || error.statusText === undefined ? error.message : error.statusText
            )" />
        <!-- eslint-disable-next-line vue/no-v-html -->
        <pre v-if="'stack' in error" v-html="error.stack" />
        <pre v-if="'toJSON' in error">{{ JSON.stringify(error.toJSON(), null, 4) }}</pre>
    </div>
</NuxtLayout>
</template>

<script setup lang="ts">
import type { NuxtError } from '#app';
import _ from 'lodash';

defineProps<{ error: NuxtError }>();
</script>
