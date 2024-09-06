import type { RouteLocationNormalized, RouterScrollBehavior } from 'vue-router';
import _ from 'lodash';

export const postListItemScrollPosition = (route: RouteLocationNormalized)
: Extract<ReturnType<RouterScrollBehavior>, { el: string | Element }> & { el: string } | false => {
    const hash = route.hash.slice(1);
    if (_.isEmpty(hash))
        return false;

    return { // https://stackoverflow.com/questions/37270787/uncaught-syntaxerror-failed-to-execute-queryselector-on-document
        el: `.post-render-list[data-cursor='${getRouteCursorParam(route)}'] [id='${hash}']`,
        top: hash.startsWith('tid/') ? 0 : replyTitleStyle().topWithoutMargin()
    };
};
const scrollToPostListItem = (el: Element) => {
    /** simply invoke {@link Element.scrollIntoView()} for only once will scroll the element to the top of the viewport */
    // and then some other elements above it such as img[loading='lazy'] may change its box size
    // that would lead to reflow resulting in the element being pushed down or up out of viewport
    /** due to {@link document.scrollingElement.scrollTop()} changed a lot */
    const tryScroll = () => {
        const abortRetries = () => { removeEventListener('scrollend', tryScroll) };
        setTimeout(abortRetries, 10000);

        /** not using a passive callback by {@link IntersectionObserver} to prevent {@link Element.getBoundingClientRect()} */
        // caused force reflow due to it will only emit once the configured thresholds are reached
        // thus the top offset might be far from 0 that is top aligned with viewport when the callback is called
        // since the element is still near the bottom of viewport at that point of time
        // even if the thresholds steps by each percentage like [0.01, 0.02, ..., 1] to let triggers callback more often
        // 1% of a very high element is still a big number that may not emit when scrolling ends
        // and the element reached the top of viewport
        const elTop = el.getBoundingClientRect().top;
        const replyTitleTopOffset = replyTitleStyle().topWithoutMargin();
        if (!el.isConnected // dangling reference to element that already removed from the document
            || window.innerHeight + window.scrollY + (window.innerHeight * 0.01) // at most 1dvh tolerance
                >= document.documentElement.scrollHeight // https://stackoverflow.com/questions/3962558/javascript-detect-scroll-end/4638434#comment137130726_4638434
            || (elTop > 0 // element is below the top of viewport
                && Math.abs(elTop) < replyTitleTopOffset + (window.innerHeight * 0.05))) // at most 5dvh tolerance
            abortRetries();
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
