<template>
    <div :data-cursor="posts.pages.currentCursor" class="post-render-list pb-3">
        <div v-for="thread in posts.threads" :key="thread.tid"
             :data-post-id="thread.tid" class="mt-3 card" :id="`t${thread.tid}`">
            <div :ref="el => elementRefsStore.pushOrClear('<RendererList>.thread-title', el as Element | null)"
                 class="thread-title shadow-sm card-header sticky-top">
                <div class="thread-title-inline-start row flex-nowrap">
                    <div class="thread-title-inline-start-title-wrapper col-auto flex-shrink-1 w-100 h-100 d-flex">
                        <BadgeThread :thread="thread" />
                        <h6 class="thread-title-inline-start-title overflow-hidden text-nowrap">{{ thread.title }}</h6>
                    </div>
                    <div class="col-auto badge bg-light">
                        <RouterLink :to="{ name: 'post/tid', params: { tid: thread.tid } }"
                                    class="badge bg-light rounded-pill link-dark">只看此帖</RouterLink>
                        <PostCommonMetadataIconLinks :post="thread"
                                                     postTypeID="tid" :postIDSelector="() => thread.tid" />
                        <BadgePostTime :time="thread.postedAt" tippyPrefix="发帖时间：" badgeColor="success" />
                    </div>
                </div>
                <div class="row justify-content-between mt-2">
                    <div class="col-auto d-flex gap-1 align-items-center">
                        <span data-tippy-content="回复量" class="badge bg-secondary">
                            <FontAwesomeIcon icon="comment-alt" class="me-1" />{{ thread.replyCount }}
                        </span>
                        <span data-tippy-content="浏览量" class="badge bg-info">
                            <FontAwesomeIcon icon="eye" class="me-1" />{{ thread.viewCount }}
                        </span>
                        <span v-if="thread.shareCount !== 0" data-tippy-content="分享量" class="badge bg-info">
                            <FontAwesomeIcon icon="share-alt" class="me-1" /> {{ thread.shareCount }}
                        </span>
                        <span data-tippy-content="赞踩量" class="badge bg-info">
                            <FontAwesomeIcon icon="thumbs-up" class="me-1" /> {{ thread.agreeCount }}
                            <FontAwesomeIcon icon="thumbs-down" class="me-1" /> {{ thread.disagreeCount }}
                        </span>
                        <span v-if="thread.zan !== null" :data-tippy-content="`
                            点赞量：${thread.zan.num}<br />
                            最后点赞时间：${DateTime.fromSeconds(Number(thread.zan.last_time))
                        .toRelative({ round: false })}
                            ${DateTime.fromSeconds(Number(thread.zan.last_time))
                        .toLocaleString(DateTime.DATETIME_FULL_WITH_SECONDS)}<br />
                            近期点赞用户：${thread.zan.user_id_list}<br />`" class="badge bg-info">
                            <!-- todo: fetch users info in zan.user_id_list -->
                            <FontAwesomeIcon icon="thumbs-up" class="me-1" /> 旧版客户端赞
                        </span>
                        <span v-if="thread.geolocation !== null" data-tippy-content="发帖位置" class="badge bg-info">
                            <FontAwesomeIcon icon="location-arrow" class="me-1" /> {{ thread.geolocation }}
                            <!-- todo: unknown json struct -->
                        </span>
                    </div>
                    <div class="col-auto badge bg-light" role="group">
                        <RouterLink :to="userRoute(thread.authorUid)">
                            <span v-if="thread.latestReplierUid !== thread.authorUid"
                                  class="fw-normal link-success">楼主：</span>
                            <span v-else class="fw-normal link-info">楼主兼最后回复：</span>
                            <span class="fw-bold link-dark">{{ renderUsername(thread.authorUid) }}</span>
                        </RouterLink>
                        <BadgeUser v-if="getUser(thread.authorUid).currentForumModerator !== null"
                                   :user="getUser(thread.authorUid)" />
                        <template v-if="thread.latestReplierUid === null">
                            <span class="fw-normal link-secondary">最后回复：</span>
                            <span class="fw-bold link-dark">未知用户</span>
                        </template>
                        <template v-else-if="thread.latestReplierUid !== thread.authorUid">
                            <RouterLink :to="userRoute(thread.latestReplierUid)" class="ms-2">
                                <span class="fw-normal link-secondary">最后回复：</span>
                                <span class="fw-bold link-dark">{{ renderUsername(thread.latestReplierUid) }}</span>
                            </RouterLink>
                        </template>
                        <BadgePostTime :time="thread.latestReplyPostedAt"
                                       tippyPrefix="最后回复时间：" badgeColor="secondary" />
                    </div>
                </div>
            </div>
            <div v-for="reply in thread.replies" :key="reply.pid" :data-post-id="reply.pid" :id="reply.pid.toString()">
                <div :ref="el => elementRefsStore.pushOrClear('<RendererList>.reply-title', el as Element | null)"
                     class="reply-title sticky-top card-header">
                    <div class="d-inline-flex gap-1 h5">
                        <span class="badge bg-secondary">{{ reply.floor }}楼</span>
                        <span v-if="reply.subReplyCount > 0" class="badge bg-info">
                            {{ reply.subReplyCount }}条<FontAwesomeIcon icon="comment-dots" />
                        </span>
                        <!-- TODO: implement these reply's property
                            <span>fold:{{ reply.isFold }}</span>
                            <span>{{ reply.agree }}</span>
                            <span>{{ reply.sign }}</span>
                            <span>{{ reply.tail }}</span>
                        -->
                    </div>
                    <div class="float-end badge bg-light">
                        <RouterLink :to="{ name: 'post/pid', params: { pid: reply.pid } }"
                                    class="badge bg-light rounded-pill link-dark">只看此楼</RouterLink>
                        <PostCommonMetadataIconLinks :post="reply"
                                                     postTypeID="pid" :postIDSelector="() => reply.pid" />
                        <BadgePostTime :time="reply.postedAt" badgeColor="primary" />
                    </div>
                </div>
                <div :ref="el => el !== null && replyElements.push(el as HTMLElement)"
                     class="reply row shadow-sm bs-callout bs-callout-info">
                    <div v-for="author in [getUser(reply.authorUid)]" :key="author.uid"
                         class="reply-author col-auto text-center sticky-top shadow-sm badge bg-light">
                        <RouterLink :to="userRoute(author.uid)" class="d-block">
                            <img :src="toTiebaUserPortraitImageUrl(author.portrait)"
                                 loading="lazy" class="tieba-user-portrait-large" />
                            <p class="my-0">{{ author.name }}</p>
                            <p v-if="author.displayName !== null && author.name !== null">{{ author.displayName }}</p>
                        </RouterLink>
                        <BadgeUser :user="getUser(reply.authorUid)" :threadAuthorUid="thread.authorUid" />
                    </div>
                    <div class="col me-2 px-1 border-start overflow-auto">
                        <div v-viewer.static class="reply-content p-2" v-html="reply.content" />
                        <template v-if="reply.subReplies.length > 0">
                            <div v-for="(subReplyGroup, _k) in reply.subReplies" :key="_k"
                                 class="sub-reply-group bs-callout bs-callout-success">
                                <ul class="list-group list-group-flush">
                                    <li v-for="(subReply, subReplyIndex) in subReplyGroup" :key="subReply.spid"
                                        @mouseenter="() => { hoveringSubReplyID = subReply.spid }"
                                        @mouseleave="() => { hoveringSubReplyID = 0 }"
                                        class="sub-reply-item list-group-item">
                                        <template v-for="author in [getUser(subReply.authorUid)]" :key="author.uid">
                                            <RouterLink v-if="subReplyGroup[subReplyIndex - 1] === undefined"
                                                        :to="userRoute(author.uid)"
                                                        class="sub-reply-author text-wrap badge bg-light">
                                                <img :src="toTiebaUserPortraitImageUrl(author.portrait)"
                                                     loading="lazy" class="tieba-user-portrait-small" />
                                                <span class="mx-2 align-middle link-dark">
                                                    {{ renderUsername(subReply.authorUid) }}
                                                </span>
                                                <BadgeUser :user="getUser(subReply.authorUid)"
                                                           :threadAuthorUid="thread.authorUid"
                                                           :replyAuthorUid="reply.authorUid" />
                                            </RouterLink>
                                            <div class="float-end badge bg-light">
                                                <div class="d-inline"
                                                     :class="{ invisible: hoveringSubReplyID !== subReply.spid }">
                                                    <PostCommonMetadataIconLinks :post="subReply"
                                                                                 postTypeID="spid"
                                                                                 :postIDSelector="() => subReply.spid" />
                                                </div>
                                                <BadgePostTime :time="subReply.postedAt" badgeColor="info" />
                                            </div>
                                        </template>
                                        <div v-viewer.static class="sub-reply-content" v-html="subReply.content" />
                                    </li>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { baseGetUser, baseRenderUsername } from './common';
import { postListItemScrollPosition } from './rendererList';
import BadgePostTime from '../badges/BadgePostTime.vue';
import BadgeThread from '../badges/BadgeThread.vue';
import BadgeUser from '../badges/BadgeUser.vue';
import PostCommonMetadataIconLinks from '../badges/PostCommonMetadataIconLinks.vue';

import type { ApiPosts } from '@/api/index.d';
import type { Reply, SubReply, Thread } from '@/api/post';
import type { BaiduUserID } from '@/api/user';
import { compareRouteIsNewQuery, setComponentCustomScrollBehaviour } from '@/router';
import type { Modify } from '@/shared';
import { convertRemToPixels, isElementNode, toTiebaUserPortraitImageUrl } from '@/shared';
import { initialTippy } from '@/shared/tippy';
import { useElementRefsStore } from '@/stores/elementRefs';
import '@/styles/bootstrapCallout.css';

import { computed, nextTick, onMounted, ref } from 'vue';
import type { RouterScrollBehavior } from 'vue-router';
import { RouterLink } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { DateTime } from 'luxon';
import * as _ from 'lodash';

const props = defineProps<{ initialPosts: ApiPosts }>();
const elementRefsStore = useElementRefsStore();
const replyElements = ref<HTMLElement[]>([]);
const hoveringSubReplyID = ref(0);
const getUser = baseGetUser(props.initialPosts.users);
const renderUsername = baseRenderUsername(getUser);
const userRoute = (uid: BaiduUserID) => ({ name: 'user/uid', params: { uid } });
const posts = computed(() => {
    const newPosts = props.initialPosts as Modify<ApiPosts, { // https://github.com/microsoft/TypeScript/issues/33591
        threads: Array<Thread & { replies: Array<Reply & { subReplies: Array<SubReply | SubReply[]> }> }>
    }>;
    newPosts.threads = newPosts.threads.map(thread => {
        thread.replies = thread.replies.map(reply => {
            // eslint-disable-next-line unicorn/no-array-reduce
            reply.subReplies = reply.subReplies.reduce<SubReply[][]>(
                (groupedSubReplies, subReply, index, subReplies) => {
                    if (_.isArray(subReply))
                        return [subReply]; // useless guard since subReply will never be an array
                    // group sub replies item by continuous and same post author
                    const previousSubReply = subReplies[index - 1] as SubReply | undefined;

                    // https://github.com/microsoft/TypeScript/issues/13778
                    if (previousSubReply !== undefined
                        && subReply.authorUid === previousSubReply.authorUid)
                        groupedSubReplies.at(-1)?.push(subReply); // append to last group
                    else
                        groupedSubReplies.push([subReply]); // new group

                    return groupedSubReplies;
                },
                []
            );

            return reply;
        });

        return thread;
    });

    return newPosts as Modify<ApiPosts, {
        threads: Array<Thread & { replies: Array<Reply & { subReplies: SubReply[][] }> }>
    }>;
});

onMounted(initialTippy);
onMounted(async () => {
    await nextTick();
    const imageWidth = convertRemToPixels(18.75); // match with .tieba-image:max-inline-size in shread/tieba.css

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
    replyElements.value.forEach(el => {
        el.attributeStyleMap.set('--sub-reply-group-count', el.querySelectorAll('.sub-reply-group').length);

        const imageLineCount = (el.querySelectorAll('.tieba-image').length * imageWidth) / contentWidth;
        el.attributeStyleMap.set('--predicted-image-height', `${Math.ceil(imageLineCount) * imageWidth}px`);

        const replyContentHeight = predictPostContentHeight(contentWidth)(el.querySelector('.reply-content'));
        el.attributeStyleMap.set('--predicted-reply-content-height', `${replyContentHeight}px`);

        const subReplyContentHeight = _.sum(
            _.toArray(el.querySelectorAll<HTMLElement>('.sub-reply-content'))
                .map(predictPostContentHeight(subReplyContentWidth))
        );
        el.attributeStyleMap.set('--predicted-sub-reply-content-height', `${subReplyContentHeight}px`);
    });

    // show diff between predicted height and actual height of each .reply after completely scroll over whole page
    // document.querySelectorAll('.reply').forEach(el => {
    //     console.log(el, el.clientHeight - /auto (\d+)px/u
    //         .exec(el.computedStyleMap().get('contain-intrinsic-block-size').toString())[1]);
    // });
});
setComponentCustomScrollBehaviour((to, from): ReturnType<RouterScrollBehavior> => {
    if (!compareRouteIsNewQuery(to, from))
        return postListItemScrollPosition(to);

    return undefined;
});
</script>

<style scoped>
.thread-title {
    block-size: 5rem; /* sync with .reply-title:inset-block-start */
    padding: .75rem 1rem .5rem 1rem;
    background-color: #f2f2f2;
}
.thread-title-inline-start {
    max-block-size: 1.6rem;
}
.thread-title-inline-start-title-wrapper {
    padding-block-start: .2rem;
}
.thread-title-inline-start-title {
    text-overflow: ellipsis;
    flex-basis: 100%;
    inline-size: 0;
}

.reply-title {
    z-index: 1019;
    inset-block-start: 5rem;
    margin-block-start: .625rem;
    border-block-start: 1px solid #ededed;
    border-block-end: 0;
    background: linear-gradient(rgba(237,237,237,1), rgba(237,237,237,.1));
}
.reply {
    padding: .625rem;
    border-block-start: 0;
    content-visibility: auto;
    --sub-reply-group-count: 0;
    --predicted-image-height: 0px;
    --predicted-reply-content-height: 0px;
    --predicted-sub-reply-content-height: 0px;
    contain-intrinsic-block-size: auto max(11rem, (var(--sub-reply-group-count) * 4rem) + var(--predicted-image-height)
        + var(--predicted-reply-content-height) + var(--predicted-sub-reply-content-height));
}
.reply-author {
    z-index: 1018;
    inset-block-start: 8rem;
    padding: .25rem;
    font-size: 1rem;
    line-height: 150%;
}

.sub-reply-group {
    margin-block-start: .5rem;
    margin-inline-start: .5rem;
    padding: .25rem;
}
.sub-reply-item {
    padding: .125rem;
}
.sub-reply-item > * {
    padding: .25rem;
}
.sub-reply-author {
    font-size: .9rem;
}
</style>
