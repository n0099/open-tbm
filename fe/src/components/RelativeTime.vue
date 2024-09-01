<template>
<span :ref="el => relativeEl = (el as HTMLElement)">
    <template v-if="hydrationStore.isHydratingOrSSR || !relativeElIsVisible">
        {{ dateTimeInChina.toLocaleString({
            year: 'numeric',
            ...keysWithSameValue(['month', 'day', 'hour', 'minute', 'second'], '2-digit')
        }) }}
    </template>
    <template v-else>
        {{ current.toRelative({ base: relativeTo, round: false }) }}
    </template>
</span>
</template>

<script setup lang="ts">
import type { DateTime } from 'luxon';

const props = defineProps<{
    dateTime: DateTime<true>,
    relativeTo?: DateTime<true>
}>();
const hydrationStore = useHydrationStore();
const relativeEl = ref<HTMLElement>();
const relativeElIsVisible = ref(false);
const dateTimeInChina = computed(() => setDateTimeZoneAndLocale()(props.dateTime));

const { pause, resume } = useIntersectionObserver(
    relativeEl,
    ([{ isIntersecting }]) => {
        relativeElIsVisible.value = isIntersecting;
    }
);
watchEffect(() => {
    if (props.relativeTo === undefined)
        pause();
    else
        resume();
});
</script>
