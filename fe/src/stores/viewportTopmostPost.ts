import type { StyleObserver } from 'style-observer';
import _ from 'lodash';

export const useViewportTopmostPostStore = defineStore('viewportTopmostPost', () => {
    interface TopmostPost { cursor: Cursor, tid: Tid, pid?: Pid }
    const viewportTopmostPost = ref<TopmostPost>();
    const extractTopmostPost = (el: HTMLElement): TopmostPost => {
        const { cursor = '', tid, pid } = el.dataset;

        return { cursor, tid: Number(tid), pid: undefinedOr(pid, Number) };
    };
    type UsingImplement = Promise<(stickyTitleEl: Ref<HTMLElement | undefined | null>, newTopmostPost: TopmostPost, topOffset?: number) => void>;
    const usingScrollState = async (): UsingImplement => {
        const stuckPosts = ref<TopmostPost[]>([]); // https://github.com/w3c/csswg-drafts/issues/12302
        type Record = Parameters<ConstructorParameters<typeof StyleObserver>[0]>[0];
        const compareTopmostPosts = (newTopmostPost: TopmostPost) => (topmostPost: TopmostPost) =>
            _.isEqual(topmostPost, newTopmostPost);
        const observer = new (await import('style-observer')).StyleObserver(_.throttle((records: Record) => {
            const findStuckPosts = (el: HTMLElement) => compareTopmostPosts(extractTopmostPost(el));
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
            let cleanup = noop;
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
        const onIntersect = (entries: IntersectionObserverEntry[]) => {
            _.orderBy(entries, entry => entry.time) // https://github.com/vueuse/vueuse/issues/4197
                .forEach(entry => {
                    const elWithDataset = entry.target.querySelector('.sticky-stuck-indicator');
                    if (elWithDataset === null)
                        return;
                    const newTopmostPost = extractTopmostPost(elWithDataset as HTMLElement);
                    if (entry.isIntersecting
                        && !(newTopmostPost.pid === undefined // prevent thread overwrite its reply
                            && viewportTopmostPost.value?.tid === newTopmostPost.tid))
                        viewportTopmostPost.value = newTopmostPost;
                });
        };

        // bottom: -100% will only trigger when reaching the top border of root that defaults to viewport
        // https://stackoverflow.com/questions/16302483/event-to-detect-when-positionsticky-is-triggered
        // https://stackoverflow.com/questions/54807535/intersection-observer-api-observe-the-center-of-the-viewport
        // https://web.archive.org/web/20240111160426/https://wilsotobianco.com/experiments/intersection-observer-playground/
        // eslint-disable-next-line compat/compat
        const observerForZeroTopOffset = new IntersectionObserver(onIntersect, { rootMargin: '0px 0px -100% 0px' });
        // eslint-disable-next-line compat/compat
        let observerForNonZeroTopOffset = new IntersectionObserver(noop);

        return (stickyTitleEl, newTopmostPost, topOffset = 0) => {
            let stopExistingWindowHeightWatcher = noop;
            watchImmediate(() => toValue(stickyTitleEl), (currentTarget, originalTarget) => {
                if (!_.isNil(originalTarget)) {
                    (topOffset === 0 ? observerForZeroTopOffset : observerForNonZeroTopOffset)
                        .unobserve(originalTarget);
                }

                if (!_.isNil(currentTarget)) {
                    if (topOffset === 0) {
                        observerForZeroTopOffset.observe(currentTarget);
                    } else {
                        stopExistingWindowHeightWatcher();
                        let stopExistingObserver = noop;
                        stopExistingWindowHeightWatcher = watchDebounced(windowHeight, () => {
                            stopExistingObserver();

                            // bottom: additional +topOffset and not using -100% to fix https://bugzilla.mozilla.org/show_bug.cgi?id=1918017
                            // top: -topOffset will move down the trigger line below the top border to match with its offset
                            const rootMargin = `${-topOffset}px 0px ${-windowHeight.value + topOffset}px 0px`;
                            observerForNonZeroTopOffset = new IntersectionObserver(onIntersect, { rootMargin });
                            observerForNonZeroTopOffset.observe(currentTarget);
                            stopExistingObserver = () => { observerForNonZeroTopOffset.disconnect() };
                        }, { debounce: 5000, immediate: true });
                    }
                };
            }, { flush: 'post' });
        };
    };
    const implement = (async (): UsingImplement => {
        if (import.meta.client && CSS.supports('container-type', 'scroll-state'))
            return usingScrollState();
        if ('IntersectionObserver' in globalThis)
            return usingIntersectionObserver();

        return noop;
    })();
    const observe = async (newTopmostPost: TopmostPost, topOffset = 0) => {
        const stickyTitleEl = ref<HTMLElement | null>();
        (await implement)(stickyTitleEl, newTopmostPost, topOffset);

        return { stickyTitleEl };
    };

    return { viewportTopmostPost, observe };
});
