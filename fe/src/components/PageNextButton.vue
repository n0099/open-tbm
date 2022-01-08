<template>
    <div class="p-4">
        <div class="row align-items-center">
            <div class="col"><hr /></div>
            <div class="w-auto">
                <RouterLink :to="pageRoutes.next" class="btn btn-secondary">
                    <span class="h4">下一页</span>
                </RouterLink>
            </div>
            <div class="col"><hr /></div>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { RouterLink } from 'vue-router';

export default defineComponent({
    components: { RouterLink },
    props: {
        pageRoutes: { type: Object as PropType<{ next: RouteLocationRaw }>, required: true }
    }
});
</script>
