import { getRouteCursorParam } from '@/router';
import { convertRemToPixels } from '@/shared';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import * as _ from 'lodash';

export const getReplyTitleTopOffset = () =>
    convertRemToPixels(5) - convertRemToPixels(0.625); // inset-block-start and margin-block-start
export const postListItemScrollPosition = (route: RouteLocationNormalizedLoaded)
    : (ScrollToOptions & { el: string }) | false => {
    const hash = route.hash.slice(1);
    if (_.isEmpty(hash))
        return false;

    return { // https://stackoverflow.com/questions/37270787/uncaught-syntaxerror-failed-to-execute-queryselector-on-document
        el: `.post-render-list[data-cursor='${getRouteCursorParam(route)}'] [id='${hash}']`,
        top: hash.startsWith('t') ? 0 : getReplyTitleTopOffset()
    };
};
