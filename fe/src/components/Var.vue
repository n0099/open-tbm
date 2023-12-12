<template>
    <slot :scope="scope" />
</template>

<script setup lang="ts" generic="T">
// https://github.com/posva/vue-local-scope/issues/17#issuecomment-1839385652
defineProps<{ scope: T }>();
</script>
