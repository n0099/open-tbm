export const supportCSM = useSupported(() => 'computedStyleMap' in document.documentElement);
export const supportIntersectionObserver = useSupported(() => 'IntersectionObserver' in globalThis);
export const supportScrollState = useSupported(() => import.meta.client && CSS.supports('container-type', 'scroll-state'));
