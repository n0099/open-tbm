export const useElementRefStore = defineStore('elementRef', () => {
    const refs = ref<Record<string, HTMLElement[] | undefined>>({});
    const get = (key: string): HTMLElement[] => refs.value[key] ?? [];
    const set = (key: string, elements: HTMLElement[]) => {
        refs.value[key] = elements;
    };
    const push = (key: string, el: HTMLElement) => {
        const elements = refs.value[key];
        if (elements === undefined)
            refs.value[key] = [el];
        else
            elements.push(el);
    };
    const clear = (key: string) => { refs.value[key] = undefined };
    const pushOrClear = (key: string, el: HTMLElement | null) => {
        if (el === null)
            clear(key);
        else
            push(key, el);
    };

    return { refs, get, set, push, clear, pushOrClear };
});
