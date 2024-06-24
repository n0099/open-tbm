import Noty from 'noty';

export const notyShow = (type: Noty.Type, text: string) => {
    if (!import.meta.client)
        return;

    // we can't declare global timeout like `window.noty = new Noty({...});`
    // due to https://web.archive.org/web/20201218224752/https://github.com/needim/noty/issues/455
    new Noty({ timeout: 5000, type, text }).show();
};

// https://stackoverflow.com/questions/986937/how-can-i-get-the-browsers-scrollbar-sizes/986977#986977
export const scrollBarWidth = computed(() => {
    if (useHydrationStore().isHydratingOrSSR())
        return '16px'; // assumed default width
    const inner = document.createElement('p');
    inner.style.width = '100%';
    inner.style.height = '200px';

    const outer = document.createElement('div');
    outer.style.position = 'absolute';
    outer.style.top = '0px';
    outer.style.left = '0px';
    outer.style.visibility = 'hidden';
    outer.style.width = '200px';
    outer.style.height = '150px';
    outer.style.overflow = 'hidden';
    outer.append(inner);

    document.body.append(outer);
    const w1 = inner.offsetWidth;
    outer.style.overflow = 'scroll';
    let w2 = inner.offsetWidth;
    if (w1 === w2)
        w2 = outer.clientWidth;
    outer.remove();

    return `${w1 - w2}px`;
});
