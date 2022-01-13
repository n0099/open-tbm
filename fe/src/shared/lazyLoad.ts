import LazyLoad from 'vanilla-lazyload';
// eslint-disable-next-line @typescript-eslint/naming-convention
export const lazyLoadInstance = new LazyLoad({ use_native: true });
export const lazyLoadUpdate = () => { lazyLoadInstance.update() };
