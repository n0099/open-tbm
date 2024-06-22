import _ from 'lodash';

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
        const replyTitleTopOffset = getReplyTitleTopOffset();
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
export const guessReplyContainIntrinsicBlockSize = (replyElements: HTMLElement[]) => {
    const imageWidth = convertRemToPixels(18.75); // match with .tieba-ugc-image:max-inline-size in components/Post/renderers/PostContentRenderer.vue

    // block-size of .reply-content should be similar when author usernames are also similar, so only takes the first element
    const contentEl = document.querySelector<HTMLElement>('.reply-content');
    if (contentEl === null)
        return;

    const getCSSPropertyInPixels = (el: HTMLElement, property: string) =>
        (el.computedStyleMap().get(property) as CSSNumericValue).to('px').value;
    const getInnerWidth = (el: HTMLElement | null) => (el === null
        ? 0
        : el.clientWidth - getCSSPropertyInPixels(el, 'padding-left')
            - getCSSPropertyInPixels(el, 'padding-right'));
    const contentWidth = getInnerWidth(contentEl);
    const subReplyContentWidth = getInnerWidth(document.querySelector('.sub-reply-content'));

    const contentLineHeightUnitValue = contentEl.computedStyleMap().get('line-height') as CSSUnitValue;
    const contentLineHeight = contentLineHeightUnitValue.unit === 'number'
        ? convertRemToPixels(contentLineHeightUnitValue.value)
        : contentLineHeightUnitValue.to('px').value;

    // regex based wcwidth(3)
    // https://en.wikipedia.org/wiki/Duospaced_font also try https://github.com/sindresorhus/get-east-asian-width
    // or https://github.com/tc39/proposal-regexp-unicode-property-escapes/issues/28 when available in the future
    const scriptRegex: Record<string, [number, RegExp]> = { // https://en.wikipedia.org/wiki/Template:ISO_15924_script_codes_and_related_Unicode_data

        // range U+0021-U+007E https://www.compart.com/en/unicode/block/U+0000 aka ASCII
        latin: [0.5, /([\u0021-\u007E]|\p{Script=Latn})+/gu],

        // block U+3000-U+303F https://www.compart.com/en/unicode/block/U+3000 contains codepoints with \p{Script=Zyyy}
        // block U+FF01-U+FF60 U+FFE0-U+FFE6 https://codepoints.net/halfwidth_and_fullwidth_forms contains many scripts
        // including \p{Script=Latn} so sum up all scripts may count some code point more than once due to CP range overlaps
        CJK: [1, /([\u3000-\u303F]|[\uFF01-\uFF60]|[\uFFE0-\uFFE6]|\p{Script=Hani}|\p{Script=Hang}|\p{Script=Hira}|\p{Script=Kana})+/gu]
    };
    const calcColumnWidth = (source: string, column: number, regex: RegExp) =>
        _.sumBy([...source.matchAll(regex)].map(matches => matches[0]), 'length') * convertRemToPixels(column);

    type StringArrayTree = Array<string | StringArrayTree>;
    const elementTreeTextContentLines = (el: ChildNode): StringArrayTree =>
        // eslint-disable-next-line unicorn/no-array-reduce
        _.toArray(el.childNodes).reduce<StringArrayTree>((acc, cur) => {
            const getTextContent = () => (isElementNode(cur) && cur.tagName === 'BR' ? '\n' : cur.textContent ?? '');
            acc.push(cur.childNodes.length > 0 ? elementTreeTextContentLines(cur) : getTextContent());

            return acc;
        }, []);
    const predictPostContentHeight = (containerWidth: number) => (el: HTMLElement | null): number => {
        if (el === null)
            return 0;
        const lineCount = _.chain(elementTreeTextContentLines(el).flat())
            .filter() // remove empty strings from elements with no content like <img>
            .join('') // single line text split by inline elements like <span>
            .split('\n') // from <br>
            .sumBy(line => Math.ceil((calcColumnWidth(line, ...scriptRegex.latin)
                + calcColumnWidth(line, ...scriptRegex.CJK)) / containerWidth))
            .value();

        return Math.round(Math.ceil(lineCount) * contentLineHeight);
    };
    replyElements.forEach(el => {
        el.attributeStyleMap.set('--sub-reply-group-count', el.querySelectorAll('.sub-reply-group').length);

        const imageLineCount = (el.querySelectorAll('.tieba-ugc-image').length * imageWidth) / contentWidth;
        el.attributeStyleMap.set('--predicted-image-height', `${Math.ceil(imageLineCount) * imageWidth}px`);

        const replyContentHeight = predictPostContentHeight(contentWidth)(el.querySelector('.reply-content'));
        el.attributeStyleMap.set('--predicted-reply-content-height', `${replyContentHeight}px`);

        const subReplyContentHeight = _.sum(
            _.toArray(el.querySelectorAll<HTMLElement>('.sub-reply-content'))
                .map(predictPostContentHeight(subReplyContentWidth))
        );
        el.attributeStyleMap.set('--predicted-sub-reply-content-height', `${subReplyContentHeight}px`);
    });

    // show diff between predicted height and actual height of each `.reply` after complete scroll over whole page
    // document.querySelectorAll('.reply').forEach(el => {
    //     console.log(el, el.clientHeight - /auto (\d+)px/u
    //         .exec(el.computedStyleMap().get('contain-intrinsic-block-size').toString())[1]);
    // });
};
