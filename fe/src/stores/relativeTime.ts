import type { ToRelativeUnit } from 'luxon';
import { DateTime, Duration } from 'luxon';

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

    return { timers, registerTimerDep };
});
