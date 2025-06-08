import type { StyleObserver } from 'style-observer';
import _ from 'lodash';

export const useViewportTopmostPostStore = defineStore('viewportTopmostPost', () => {
    interface TopmostPost { cursor: Cursor, tid: Tid, pid?: Pid }
    const viewportTopmostPost = ref<TopmostPost>();
    type UsingImplement = Promise<(stickyTitleEl: Ref<HTMLElement | undefined>, newTopmostPost: TopmostPost, topOffset?: number) => void>;
    const usingScrollState = async (): UsingImplement => {
        const stuckPosts = ref<TopmostPost[]>([]); // https://github.com/w3c/csswg-drafts/issues/12302
        type Record = Parameters<ConstructorParameters<typeof StyleObserver>[0]>[0];
        const compareTopmostPosts = (newTopmostPost: TopmostPost) => (topmostPost: TopmostPost) =>
            _.isEqual(topmostPost, newTopmostPost);
        const observer = new (await import('style-observer')).StyleObserver(_.throttle((records: Record) => {
            const findStuckPosts = (el: HTMLElement) => {
                const { cursor = '', tid, pid } = el.dataset;
                const newTopmostPost: TopmostPost = { cursor, tid: Number(tid), pid: undefinedOr(pid, Number) };

                return compareTopmostPosts(newTopmostPost);
            };
            records.forEach(entry => {
                viewportTopmostPost.value = entry.value === "'true'"
                    ? stuckPosts.value.find(findStuckPosts(entry.target as HTMLElement))
                    : stuckPosts.value[stuckPosts.value.findIndex(findStuckPosts(entry.target as HTMLElement)) - 1];
            });
        }, 300, { trailing: true }));

        return (stickyTitleEl, newTopmostPost) => {
            // assume the invoking order of current function is the same as https://en.wikipedia.org/wiki/Depth-first_search order of all posts tree
            if (!stuckPosts.value.some(compareTopmostPosts(newTopmostPost)))
                stuckPosts.value.push(newTopmostPost);

            // https://github.com/vueuse/vueuse/blob/ae573a0fb2b6dc0ef7a6a9d349f011984f49ae48/packages/core/useIntersectionObserver/index.ts#L68-L96
            // eslint-disable-next-line @typescript-eslint/no-empty-function
            let cleanup = () => {};
            watch(stickyTitleEl, () => {
                cleanup();
                const postIdEl = stickyTitleEl.value?.querySelector('.sticky-stuck-indicator');
                if (!_.isNil(postIdEl)) {
                    observer.observe(postIdEl, '--is-stuck');
                    cleanup = () => { observer.unobserve(postIdEl) };
                }
            }, { flush: 'post' });
        };
    };
    // eslint-disable-next-line @typescript-eslint/require-await
    const usingIntersectionObserver = async (): UsingImplement => {
        const { height: windowHeight } = useWindowSize();

        return (stickyTitleEl, newTopmostPost, topOffset = 0) => {
            const onIntersect = (entries: IntersectionObserverEntry[]) => {
                _.orderBy(entries, entry => entry.time) // https://github.com/vueuse/vueuse/issues/4197
                    .filter(entry => entry.isIntersecting
                        && !(newTopmostPost.pid === undefined // prevent thread overwrite its reply
                            && viewportTopmostPost.value?.tid === newTopmostPost.tid))
                    .forEach(() => { viewportTopmostPost.value = newTopmostPost });
            };
            if (topOffset === 0) {
                // bottom: -100% will only trigger when reaching the top border of root that defaults to viewport
                // https://stackoverflow.com/questions/16302483/event-to-detect-when-positionsticky-is-triggered
                // https://stackoverflow.com/questions/54807535/intersection-observer-api-observe-the-center-of-the-viewport
                // https://web.archive.org/web/20240111160426/https://wilsotobianco.com/experiments/intersection-observer-playground/
                useIntersectionObserver(stickyTitleEl, onIntersect, { rootMargin: '0px 0px -100% 0px' });
            } else {
                // eslint-disable-next-line @typescript-eslint/no-empty-function
                let stopExistingIntersectionObserver = () => {};
                watchDebounced(windowHeight, () => {
                    stopExistingIntersectionObserver();

                    // bottom: additional +topOffset and not using -100% to fix https://bugzilla.mozilla.org/show_bug.cgi?id=1918017
                    // top: -topOffset will move down the trigger line below the top border to match with its offset
                    const rootMargin = `${-topOffset}px 0px ${-windowHeight.value + topOffset}px 0px`;
                    const { stop } = useIntersectionObserver(stickyTitleEl, onIntersect, { rootMargin });
                    stopExistingIntersectionObserver = stop;
                }, { debounce: 5000, immediate: true });
            }
        };
    };
    const implement = import.meta.client && CSS.supports('container-type', 'scroll-state')
        ? usingScrollState()
        : usingIntersectionObserver();
    const observe = async (newTopmostPost: TopmostPost, topOffset = 0) => {
        const stickyTitleEl = ref<HTMLElement>();
        (await implement)(stickyTitleEl, newTopmostPost, topOffset);

        return { stickyTitleEl };
    };

    return { viewportTopmostPost, observe };
});
