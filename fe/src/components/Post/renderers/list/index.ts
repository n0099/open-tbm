import { getRouteCursorParam } from '@/router';
import { convertRemToPixels } from '@/shared';
import type { RouteLocationNormalized } from 'vue-router';
import * as _ from 'lodash-es';

export const getReplyTitleTopOffset = () =>
    convertRemToPixels(5) - convertRemToPixels(0.625); // inset-block-start and margin-block-start
export const postListItemScrollPosition = (route: RouteLocationNormalized)
    : (ScrollToOptions & { el: string }) | false => {
    const hash = route.hash.slice(1);
    if (_.isEmpty(hash))
        return false;

    return { // https://stackoverflow.com/questions/37270787/uncaught-syntaxerror-failed-to-execute-queryselector-on-document
        el: `.post-render-list[data-cursor='${getRouteCursorParam(route)}'] [id='${hash}']`,
        top: hash.startsWith('t') ? 0 : getReplyTitleTopOffset()
    };
};
const scrollToPostListItem = (el: Element) => {
    /** simply invoke {@link Element.scrollIntoView()} for only once will scroll the element to the top of the viewport */
    // and then some other elements above it such as img[loading='lazy'] may change its box size
    // that would lead to reflow resulting in the element being pushed down or up out of viewport
    /** due to {@link document.scrollingElement.scrollTop()} changed a lot */
    const tryScroll = () => {
        /** not using a passive callback by IntersectionObserverto to prevent {@link Element.getBoundingClientRect()} caused force reflow */
        // due to it will only emit once the configured thresholds are reached
        // thus the top offset might be far from 0 that is top aligned with viewport when the callback is called
        // since the element is still near the bottom of viewport at that point of time
        // even if the thresholds steps by each percentage like [0.01, 0.02, ..., 1] to let triggers callback more often
        // 1% of a very high element is still a big number that may not emit when scrolling ends
        // and the element reached the top of viewport
        const elTop = el.getBoundingClientRect().top;
        const replyTitleTopOffset = getReplyTitleTopOffset();
        if (Math.abs(elTop) < replyTitleTopOffset + (window.innerHeight * 0.05)) // at most 5dvh tolerance
            removeEventListener('scrollend', tryScroll);
        else
            document.documentElement.scrollBy({ top: elTop - replyTitleTopOffset });
    };
    tryScroll();
    addEventListener('scrollend', tryScroll);
};
export const scrollToPostListItemByRoute = (route: RouteLocationNormalized) => {
    const scrollPosition = postListItemScrollPosition(route);
    if (scrollPosition === false)
        return;
    const el = document.querySelector(scrollPosition.el);
    if (el === null)
        return;
    requestIdleCallback(function retry(deadline) {
        if (deadline.timeRemaining() > 0)
            scrollToPostListItem(el);
        else
            requestIdleCallback(retry);
    });
};
