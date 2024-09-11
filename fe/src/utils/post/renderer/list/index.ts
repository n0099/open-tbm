import _ from 'lodash';

export type ThreadWithGroupedSubReplies<AdditionalSubReply extends SubReply = never> =
    Thread & { replies: Array<Reply & { subReplies: Array<AdditionalSubReply | SubReply[]> }> };

const remWithPixels = (rem: number) => ({
    rem,
    get remString() { return `${this.rem}rem` },
    get px() { return convertRemToPixels(this.rem) }
});
export const replyTitleStyle = {
    insetBlockStart: remWithPixels(5),
    marginBlockStart: remWithPixels(0.625),
    get topWithoutMargin() {
        return remWithPixels(this.insetBlockStart.rem - this.marginBlockStart.rem);
    }
};
export const tiebaUGCImageMaxSize = remWithPixels(18.75);
export const guessReplyContainIntrinsicBlockSize = (replyElements: HTMLElement[]) => {
    // block-size of .reply-content should be similar when author usernames are also similar, so only takes the first element
    const contentEl = document.querySelector<HTMLElement>('.reply-content');
    if (contentEl === null)
        return;
    const contentStyles = ((el: HTMLElement) => {
        // https://caniuse.com/mdn-api_element_computedstylemap
        // https://bugzilla.mozilla.org/show_bug.cgi?id=1857849
        // https://github.com/surma/ishoudinireadyyet.com
        // https://github.com/tylergaw/css-typed-om/
        const isCSMSupported = 'computedStyleMap' in el;
        const pixelStringToNumber = (s: string) => {
            if (!s.endsWith('px'))
                throw new Error(`Unit of '${s}' is not in pixels`);

            return Number(removeEnd(s, 'px')); // parseInt() will also remove any suffix
        };

        const getCSSPropertyInPixels = (el: HTMLElement, property: string) => (isCSMSupported
            ? (el.computedStyleMap().get(property) as CSSNumericValue).to('px').value
            : pixelStringToNumber(getComputedStyle(el).getPropertyValue(property)));
        const getInnerWidth = (el: HTMLElement | null) => (el === null
            ? 0
            : el.clientWidth - getCSSPropertyInPixels(el, 'padding-left')
                - getCSSPropertyInPixels(el, 'padding-right'));

        const convertRemUnitValueToPixels = (unitValue: CSSUnitValue) => (unitValue.unit === 'number'
            ? convertRemToPixels(unitValue.value)
            : unitValue.to('px').value);
        const lineHeight = isCSMSupported
            ? convertRemUnitValueToPixels(el.computedStyleMap().get('line-height') as CSSUnitValue)
            : convertRemToPixels(pixelStringToNumber(getComputedStyle(el).lineHeight));

        return {
            width: getInnerWidth(el),
            subReply: { width: getInnerWidth(document.querySelector('.sub-reply-content')) },
            lineHeight
        };
    })(contentEl);

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
        _.sumBy([...source.matchAll(regex)]
            .map(matches => matches[0]), 'length') * convertRemToPixels(column);

    type StringArrayTree = Array<string | StringArrayTree>;
    const elementTreeTextContentLines = (el: ChildNode): StringArrayTree =>
        // eslint-disable-next-line unicorn/no-array-reduce
        [...el.childNodes].reduce<StringArrayTree>((acc, cur) => {
            const getTextContent = () =>
                (isElementNode(cur) && cur.tagName === 'BR' ? '\n' : cur.textContent ?? '');
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

        return Math.round(Math.ceil(lineCount) * contentStyles.lineHeight);
    };
    replyElements.forEach(el => {
        // https://caniuse.com/mdn-api_htmlelement_attributestylemap
        el.style.setProperty('--sub-reply-group-count',
            el.querySelectorAll('.sub-reply-group').length.toString());

        const imageLineCount = (el.querySelectorAll('.tieba-ugc-image')
            .length * tiebaUGCImageMaxSize.px) / contentStyles.width;
        el.style.setProperty('--predicted-image-height',
            `${Math.ceil(imageLineCount) * tiebaUGCImageMaxSize.px}px`);

        const replyContentHeight = predictPostContentHeight(contentStyles.width)(el.querySelector('.reply-content'));
        el.style.setProperty('--predicted-reply-content-height', `${replyContentHeight}px`);

        const subReplyContentHeight = _.sum(
            [...el.querySelectorAll<HTMLElement>('.sub-reply-content')]
                .map(predictPostContentHeight(contentStyles.subReply.width))
        );
        el.style.setProperty('--predicted-sub-reply-content-height', `${subReplyContentHeight}px`);
    });

    // show diff between predicted height and actual height of each `.reply` after complete scroll over whole page
    // document.querySelectorAll('.reply').forEach(el => {
    //     console.log(el, el.clientHeight - /auto (\d+)px/u
    //         .exec(el.computedStyleMap().get('contain-intrinsic-block-size').toString())[1]);
    // });
};
