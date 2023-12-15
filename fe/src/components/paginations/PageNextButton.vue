<template>
    <div class="p-4">
        <div class="row align-items-center">
            <div class="col"><hr /></div>
            <div class="w-auto">
                <RouterLink :to="nextCursorRoute" class="btn btn-secondary">
                    <span class="h4">下一页</span>
                </RouterLink>
            </div>
            <div class="col"><hr /></div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router';
import { RouterLink } from 'vue-router';

defineProps<{ nextCursorRoute: RouteLocationRaw }>();
</script>
