import type { Action, Comment, DiscussionForumPosting, InteractionCounter, Person } from 'schema-dts';
import type { InfiniteData } from '@tanstack/vue-query';
import { DateTime } from 'luxon';

// https://developers.google.com/search/docs/appearance/structured-data/discussion-forum
export const usePostsSchemaOrg = (data: Ref<InfiniteData<ApiPosts['response']> | undefined>) => {
    const router = useRouter();
    const baseUrlWithDomain = useSiteConfig().url;
    const definePostComment = <T extends Post>(post: T, postIDKey: keyof T & PostIDOf<T>): Comment => ({
        /* eslint-disable @typescript-eslint/naming-convention */
        '@type': 'Comment',
        '@id': (post[postIDKey] as Tid | Pid | Spid).toString(),
        url: baseUrlWithDomain + router.resolve({
            name: `posts/${postIDKey}`,
            params: { [postIDKey]: post[postIDKey] as Tid | Pid | Spid }
        }).fullPath,
        dateCreated: DateTime.fromSeconds(post.createdAt).toISO(),
        datePublished: DateTime.fromSeconds(post.postedAt).toISO(),
        upvoteCount: post.agreeCount,
        downvoteCount: post.disagreeCount
    });

    const defineUserPerson = (uid: BaiduUserID): Exclude<Person, string> => ({
        '@type': 'Person',
        '@id': uid.toString(),
        url: baseUrlWithDomain + router.resolve(toUserRoute(uid)).fullPath
    });
    const definePostAuthorPerson = (post: Post, { getUser }: UserProvision): Pick<Comment, 'author'> => {
        const uid = post.authorUid;
        const user = getUser(uid);

        return {
            author: {
                ...defineUserPerson(uid),
                name: [user.name, user.displayName].filter(i => i !== null),
                image: toUserPortraitImageUrl(user.portrait),
                sameAs: toUserProfileUrl(user)
            }
        };
    };

    const extractContentImagesUrl = (content: PostContent | null) =>
        undefinedWhenEmpty(content?.filter(i => i.type === 3)
            .map(i => imageUrl(i.originSrc))
            .filter(i => i !== undefined));
    const extractContentUserMentions = (content: PostContent | null) =>
        undefinedWhenEmpty(content?.filter(i => i.type === 4)
            .filter(i => i.uid !== undefined)
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            .map((i): Person => ({ ...defineUserPerson(i.uid!), name: i.text })));
    const definePostContentComment = (content: PostContent | null): Partial<Comment> => ({
        text: extractContentTexts(content),
        image: extractContentImagesUrl(content),
        mentions: extractContentUserMentions(content)
    });

    const defineInteractionCounter = (action: Action['@type'], count: number): InteractionCounter => ({
        '@type': 'InteractionCounter',
        interactionType: { '@type': action } as Action,
        userInteractionCount: count
    });
    const definePostInteractionCounters = (post: Post): InteractionCounter[] => [
        defineInteractionCounter('LikeAction', post.agreeCount),
        defineInteractionCounter('DislikeAction', post.disagreeCount)
    ];

    const defineThreadDiscussionForumPosting = (
        userProvision: UserProvision,
        thread: Thread,
        firstReplyContent?: PostContent | null
    ): DiscussionForumPosting => ({
        ...definePostComment(thread, 'tid'),
        ...definePostAuthorPerson(thread, userProvision),
        ...definePostContentComment(firstReplyContent ?? null),
        '@type': 'DiscussionForumPosting',
        sameAs: tiebaPostLink(thread.tid),
        headline: thread.title,
        commentCount: thread.replyCount,
        interactionStatistic: [
            ...definePostInteractionCounters(thread),
            defineInteractionCounter('ReplyAction', thread.replyCount),
            defineInteractionCounter('ViewAction', thread.viewCount),
            defineInteractionCounter('ShareAction', thread.shareCount)
        ]
    });
    const defineReplyComment = (reply: Reply, userProvision: UserProvision): Comment => ({
        ...definePostComment(reply, 'pid'),
        ...definePostAuthorPerson(reply, userProvision),
        ...definePostContentComment(reply.content),
        sameAs: tiebaPostLink(reply.tid, reply.pid),
        parentItem: { '@type': 'Comment', '@id': reply.tid.toString() },
        commentCount: reply.subReplyCount,
        interactionStatistic: [
            ...definePostInteractionCounters(reply),
            defineInteractionCounter('ReplyAction', reply.subReplyCount)
        ]
    });
    const defineSubReplyComment = (userProvision: UserProvision) =>
        (subReply: SubReply): Comment => ({
            ...definePostComment(subReply, 'spid'),
            ...definePostAuthorPerson(subReply, userProvision),
            ...definePostContentComment(subReply.content),
            sameAs: tiebaPostLink(subReply.tid, subReply.pid, subReply.spid),
            parentItem: { '@type': 'Comment', '@id': subReply.pid.toString() },
            /* eslint-enable @typescript-eslint/naming-convention */
            interactionStatistic: definePostInteractionCounters(subReply)
        });
    useSchemaOrg(computed(() => data.value?.pages.flatMap(page => {
        const getUser = baseGetUser(page.users);
        const renderUsername = baseRenderUsername(getUser);

        return page.threads.flatMap(thread => [
            defineThreadDiscussionForumPosting({ getUser, renderUsername }, thread, thread.replies[0]?.content),
            ...thread.replies.flatMap(reply => [
                defineReplyComment(reply, { getUser, renderUsername }),
                ...reply.subReplies.map(defineSubReplyComment({ getUser, renderUsername }))
            ])
        ]);
    }) ?? []));
};
