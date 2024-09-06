import _ from 'lodash';

interface HighlightingPost { postIDKey: PostIDStr, postId: PostID }
type PostParamFunction<TReturn> = <TPost extends Post>(post: TPost, postIDKey: keyof TPost & PostIDOf<TPost>) => TReturn;

export const useHighlightPostStore = defineStore('highlightPost', () => {
    const highlightingPost = ref<HighlightingPost>();
    const set: PostParamFunction<void> = (post, postIDKey) => {
        highlightingPost.value = { postIDKey, postId: post[postIDKey] as PostID };
    };
    const unset = () => { highlightingPost.value = undefined };
    const isHighlightingPost: PostParamFunction<boolean> = (post, postIDKey) =>
        _.isEqual(highlightingPost.value, { postIDKey, postId: post[postIDKey] });

    return { highlightingPost, set, unset, isHighlightingPost };
});
