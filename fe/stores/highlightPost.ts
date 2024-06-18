import type { Pid, Post, PostID, PostIDOf, Spid, Tid } from '~/utils';
import { defineStore } from 'pinia';
import _ from 'lodash';

interface HighlightingPost { postIDKey: PostID, postId: Tid | Pid | Spid }
type PostParamFunction = <TPost extends Post>(post: TPost, postIDKey: keyof TPost & PostIDOf<TPost>) => void;

export const useHighlightPostStore = defineStore('highlightPost', () => {
    const highlightingPost = ref<HighlightingPost>();
    const set: PostParamFunction = (post, postIDKey) => {
        highlightingPost.value = { postIDKey, postId: post[postIDKey] as Tid | Pid | Spid };
    };
    const unset = () => { highlightingPost.value = undefined };
    const isHighlightingPost: PostParamFunction = (post, postIDKey) =>
        _.isEqual(highlightingPost.value, { postIDKey, postId: post[postIDKey] });

    return { highlightingPost, set, unset, isHighlightingPost };
});
