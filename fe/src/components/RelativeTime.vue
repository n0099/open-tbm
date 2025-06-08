<template>
<span ref="observeTargetEl">
    <template v-if="hydrationStore.isHydratingOrSSR || !isAlreadySeen">
        <template v-if="relativeTo === undefined">
            {{ dateTimeInShanghai.toLocaleString({
                year: 'numeric',
                ...keysWithSameValue(['month', 'day', 'hour', 'minute', 'second'], '2-digit')
            }) }}
        </template>
        <span v-else class="relative-time-placeholder d-inline-block" />
    </template>
    <span :key="updatedTimes" v-else>
        {{ dateTime.toRelative({ base: relativeTo, round: false }) }}
    </span>
</span>
</template>

<script setup lang="ts">
import type { DateTime } from 'luxon';

const { dateTime, relativeTo } = defineProps<{
    dateTime: DateTime<true>,
    relativeTo?: DateTime<true>
}>();
const hydrationStore = useHydrationStore();
const relativeTimeStore = useRelativeTimeStore();
const dateTimeInShanghai = computed(() => setDateTimeZoneAndLocale()(dateTime));
const updateTimerDep = computed(() =>
    (relativeTo === undefined ? relativeTimeStore.registerTimerDep(dateTime).value : undefined));
const updatedTimes = ref(0);
const { observeTargetEl, isVisibleRef } = relativeTimeStore.observe();
const isAlreadySeen = ref(false);
const placeholderWidth = computed(() => (dateTimeLocale.value.startsWith('zh') ? '' : '2.5rem'));

watchEffect(() => {
    const isVisible = isVisibleRef.value?.value ?? false;
    if (isVisible// is in viewport
        && isAlreadySeen.value) { // is not the initial render to prevent immediately re-render
        updatedTimes.value++; // force re-render the relativeEl
    }
    if (!isAlreadySeen.value && isVisible) {
        void updateTimerDep.value; /** track {@link updateTimerDep} as watch dep */
        isAlreadySeen.value = true; // must set AFTER the above if
    }
});
</script>

<style scoped>
.relative-time-placeholder {
    width: v-bind(placeholderWidth);
}
</style>
