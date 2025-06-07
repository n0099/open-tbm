import _ from 'lodash';

export const useViewportTopmostPostStore = defineStore('viewportTopmostPost', () => {
    interface TopmostPost { cursor: Cursor, tid: Tid, pid?: Pid }
    const viewportTopmostPost = ref<TopmostPost>();
    const { height: windowHeight } = useWindowSize();
    type UsingImplement = (stickyTitleEl: Ref<HTMLElement | undefined>, newTopmostPost: TopmostPost, topOffset?: number) => void;
    const usingScrollState: UsingImplement = (stickyTitleEl, newTopmostPost) => {
        // https://github.com/vueuse/vueuse/blob/ae573a0fb2b6dc0ef7a6a9d349f011984f49ae48/packages/core/useIntersectionObserver/index.ts#L68-L96
        // eslint-disable-next-line @typescript-eslint/no-empty-function
        let cleanup = () => {};
        watch(stickyTitleEl, async () => {
            cleanup();
            const observer = new (await import('style-observer')).StyleObserver(records => {
                records.forEach(() => { viewportTopmostPost.value = newTopmostPost });
            });
            const postIdEl = stickyTitleEl.value?.querySelector('.sticky-stuck-indicator');
            if (!_.isNil(postIdEl))
                observer.observe(postIdEl, '--is-stuck');
            cleanup = () => { observer.unobserve() };
        }, { flush: 'post' });
    };
    const usingIntersectionObserver: UsingImplement = (stickyTitleEl, newTopmostPost, topOffset = 0) => {
        const onIntersect = (entries: IntersectionObserverEntry[]) => {
            _.orderBy(entries, 'time').forEach(e => { // https://github.com/vueuse/vueuse/issues/4197
                if (e.isIntersecting
                    && !(newTopmostPost.pid === undefined // prevent thread overwrite its reply
                        && viewportTopmostPost.value?.tid === newTopmostPost.tid))
                    viewportTopmostPost.value = newTopmostPost;
            });
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
    const intersectionObserver = (newTopmostPost: TopmostPost, topOffset = 0) => {
        const stickyTitleEl = ref<HTMLElement>();
        if (import.meta.client && CSS.supports('container-type', 'scroll-state'))
            usingScrollState(stickyTitleEl, newTopmostPost);
        else
            usingIntersectionObserver(stickyTitleEl, newTopmostPost, topOffset);

        return { stickyTitleEl };
    };

    return { viewportTopmostPost, intersectionObserver };
});
