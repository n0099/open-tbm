
export const useElementRefsStore = defineStore('elementRefs', () => {
    const refs = ref<Record<string, Element[] | undefined>>({});
    const get = (key: string): Element[] => refs.value[key] ?? [];
    const set = (key: string, elements: Element[]) => {
        refs.value[key] = elements;
    };
    const push = (key: string, el: Element) => {
        const elements = refs.value[key];
        if (elements === undefined)
            refs.value[key] = [el];
        else
            elements.push(el);
    };
    const clear = (key: string) => { refs.value[key] = undefined };
    const pushOrClear = (key: string, el: Element | null) => {
        if (el === null)
            clear(key);
        else
            push(key, el);
    };

    return { refs, get, set, push, clear, pushOrClear };
});
