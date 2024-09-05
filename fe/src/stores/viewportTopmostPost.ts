import _ from 'lodash';

export const useViewportTopmostPostStore = defineStore('viewportTopmostPost', () => {
    interface TopmostPost { cursor: Cursor, tid: Tid, pid?: Pid }
    const viewportTopmostPost = ref<TopmostPost>();

    const intersectionObserver = (newTopmostPost: TopmostPost, topOffset = 0) => {
        const stickyTitleEl = ref<HTMLElement>();
        useIntersectionObserver(stickyTitleEl, entries => {
            _.orderBy(entries, 'time').forEach(e => { // https://github.com/vueuse/vueuse/issues/4197
                if (e.isIntersecting
                    && !(newTopmostPost.pid === undefined // prevent thread overwrite its reply
                        && viewportTopmostPost.value?.tid === newTopmostPost.tid))
                    viewportTopmostPost.value = newTopmostPost;
            });

        // bottom: -100% will only trigger when reaching the top border of root that defaults to viewport
        // top: -(topOffset + 1)px will move down the trigger line below the top border to match with its offset
        // the additional +1px will allow they sharing an intersection with 1px height when the stickyTitleEl is stucking
        // https://stackoverflow.com/questions/16302483/event-to-detect-when-positionsticky-is-triggered
        // https://stackoverflow.com/questions/54807535/intersection-observer-api-observe-the-center-of-the-viewport
        // https://web.archive.org/web/20240111160426/https://wilsotobianco.com/experiments/intersection-observer-playground/
        }, { rootMargin: `-${topOffset}px 0px -100% 0px` });

        return { stickyTitleEl };
    };

    return { viewportTopmostPost, intersectionObserver };
});
