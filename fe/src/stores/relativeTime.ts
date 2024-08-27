import type { ToRelativeOptions, ToRelativeUnit } from 'luxon';
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
    const registerRelative = (dateTime: DateTime, options?: ToRelativeOptions) => computed(() => {
        const relativeDuration = dateTime
            .diff(DateTime.now(), undefined, { conversionAccuracy: 'longterm' })
            .shiftTo(...units);
        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
        const { unit } = units
            .map(unit => ({ unit, value: relativeDuration.get(unit) }))
            .find(unit => unit.value !== 0)!;
        void timers[unit]; // track this computed ref as dependency of reactive timers[unit]

        return dateTime.toRelative({ ...options, round: false });
    });

    return { timers, registerRelative };
});
