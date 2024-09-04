<template>
<span :ref="el => rootEl = (el as HTMLElement)">
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

const props = defineProps<{
    dateTime: DateTime<true>,
    relativeTo?: DateTime<true>
}>();
const hydrationStore = useHydrationStore();
const relativeTimeStore = useRelativeTimeStore();
const dateTimeInShanghai = computed(() => setDateTimeZoneAndLocale()(props.dateTime));
const updateTimerDep = computed(() =>
    (props.relativeTo === undefined ? relativeTimeStore.registerTimerDep(props.dateTime).value : undefined));
const updatedTimes = ref(0);
const rootEl = ref<HTMLElement>();
const isVisible = ref(false);
let isVisibleDeounceId = 0;
const isAlreadySeen = ref(false);

useIntersectionObserver(
    rootEl,
    ([{ isIntersecting }]) => {
        clearTimeout(isVisibleDeounceId);
        isVisibleDeounceId = window.setTimeout(() => {
            isVisible.value = isIntersecting;
        }, isIntersecting ? 500 : 0);
    },
    { threshold: 1 }
);
watchEffect(() => {
    if (isVisible.value // is in viewport
        && isAlreadySeen.value) { // is not the initial render to prevent immediately re-render
        updatedTimes.value++; // force re-render the relativeEl
    }
    if (!isAlreadySeen.value && isVisible.value) {
        void updateTimerDep.value; /** track {@link updateTimerDep} as watch dep */
        isAlreadySeen.value = true; // must set AFTER the above if
    }
});
</script>
