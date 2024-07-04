import type { ApiForumThreadsID, ApiForums } from '@/api/types';
import type { Tid } from '@/utils';

export default defineSitemapEventHandler(async event => {
    const baseUrlWithDomain = useSiteConfig(event).url;
    const endpointPrefix = useRuntimeConfig().public.apiEndpointPrefix;
    const fetchOptions = {
        headers: {
            Accept: 'application/json',
            Authorization: event.headers.get('Authorization') ?? ''
        }
    };
    const forums = await $fetch<ApiForums['response']>(`${endpointPrefix}/forums`, fetchOptions);

    return (await Promise.all(forums.map(async ({ fid }) => {
        const tids: Tid[] = [];
        let hasMore = true;
        let cursor = 0;
        while (hasMore) {
            // eslint-disable-next-line no-await-in-loop
            const threads = await $fetch<ApiForumThreadsID['response']>(
                `${endpointPrefix}/forums/${fid}/threads/tid`,
                { query: { cursor }, ...fetchOptions }
            );
            tids.push(...threads.tid);
            hasMore = threads.pages.nextCursor !== null;
            cursor = threads.pages.nextCursor ?? Infinity;
        }

        return tids.map(tid => asSitemapUrl({ loc: `${baseUrlWithDomain}/posts/tid/${tid}` }));
    }))).flat();
});
