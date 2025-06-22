import type { ToRelativeUnit } from 'luxon';
import { DateTime, Duration } from 'luxon';
import _ from 'lodash';

export const useRelativeTimeStore = defineStore('relativeTime', () => {
    const units: ToRelativeUnit[] = ['months', 'weeks', 'days', 'hours'];
    const timers = reactive(keysWithSameValue(units, 0));
    if (import.meta.client) {
        (Object.keys(timers) as Array<keyof typeof timers>).forEach(unit => {
            setInterval(
                () => { timers[unit] += 1 },
                Duration.fromObject({ [unit]: 0.01 }).toMillis()
            );
        });
    }
    const registerTimerDep = (dateTime: DateTime) => computed(() => {
        const relativeDuration = dateTime
            .diff(DateTime.now(), undefined, { conversionAccuracy: 'longterm' })
            .shiftTo(...units);
        const { unit } = units
            .map(unit => ({ unit, value: relativeDuration.get(unit) }))
            .find(unit => unit.value !== 0)
            ?? { unit: 'years' };

        return timers[unit];
    });

    const elementVisibilityMap = new WeakMap<Element, { isVisible: Ref<boolean>, debounceId: TimeoutOrIntervalId }>();
    const getElementVisibility = (el: Element) => {
        const oldVisibility = elementVisibilityMap.get(el);
        if (oldVisibility === undefined) {
            const newVisibility = { isVisible: ref(false), debounceId: 0 };
            elementVisibilityMap.set(el, newVisibility);

            return newVisibility;
        }

        return oldVisibility;
    };
    const intersectionObserver = supportIntersectionObserver.value
        ? new IntersectionObserver(entries => {
            _.orderBy(entries, entry => entry.time).forEach(entry => { // https://github.com/vueuse/vueuse/issues/4197
                const visibility = getElementVisibility(entry.target);
                clearTimeout(visibility.debounceId);
                // eslint-disable-next-line unicorn/prefer-global-this
                visibility.debounceId = window.setTimeout(() => {
                    visibility.isVisible.value = entry.isIntersecting;
                }, entry.isIntersecting ? 500 : 0);
            });
        }, { threshold: 1, rootMargin: '100%' })
        : undefined;
    const observe = () => {
        const isVisibleRef = ref<Ref<boolean>>();
        const observeTargetEl = ref<Element | null>();
        if (supportIntersectionObserver.value) {
            watchImmediate(observeTargetEl, (currentTarget, originalTarget) => {
                if (!_.isNil(originalTarget))
                    intersectionObserver?.unobserve(originalTarget);
                if (!_.isNil(currentTarget)) {
                    intersectionObserver?.observe(currentTarget);
                    isVisibleRef.value = getElementVisibility(currentTarget).isVisible;
                }
            }, { flush: 'post' });
        }

        return { observeTargetEl, isVisibleRef };
    };

    return { timers, registerTimerDep, observe };
});
