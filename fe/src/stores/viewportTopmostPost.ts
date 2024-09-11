import _ from 'lodash';

export const useViewportTopmostPostStore = defineStore('viewportTopmostPost', () => {
    interface TopmostPost { cursor: Cursor, tid: Tid, pid?: Pid }
    const viewportTopmostPost = ref<TopmostPost>();
    const { height: windowHeight } = useWindowSize();
    const intersectionObserver = (newTopmostPost: TopmostPost, topOffset = 0) => {
        const stickyTitleEl = ref<HTMLElement>();
        // eslint-disable-next-line @typescript-eslint/no-empty-function
        let stopExistingIntersectionObserver = () => {};
        watchDebounced(windowHeight, () => {
            stopExistingIntersectionObserver();
            const { stop } = useIntersectionObserver(stickyTitleEl, entries => {
                _.orderBy(entries, 'time').forEach(e => { // https://github.com/vueuse/vueuse/issues/4197
                    if (e.isIntersecting
                        && !(newTopmostPost.pid === undefined // prevent thread overwrite its reply
                            && viewportTopmostPost.value?.tid === newTopmostPost.tid))
                        viewportTopmostPost.value = newTopmostPost;
                });

            // bottom: -windowHeight will only trigger when reaching the top border of root that defaults to viewport
            // additional +topOffset and not using -100% to fix https://bugzilla.mozilla.org/show_bug.cgi?id=1918017
            // top: -topOffset will move down the trigger line below the top border to match with its offset
            // https://stackoverflow.com/questions/16302483/event-to-detect-when-positionsticky-is-triggered
            // https://stackoverflow.com/questions/54807535/intersection-observer-api-observe-the-center-of-the-viewport
            // https://web.archive.org/web/20240111160426/https://wilsotobianco.com/experiments/intersection-observer-playground/
            }, { rootMargin: `${-topOffset}px 0px ${-windowHeight.value + topOffset}px 0px` });
            stopExistingIntersectionObserver = stop;
        }, { debounce: 5000, immediate: true });

        return { stickyTitleEl };
    };

    return { viewportTopmostPost, intersectionObserver };
});
