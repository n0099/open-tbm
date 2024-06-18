import { createResolver, defineNuxtModule, extendPages } from '@nuxt/kit';

export default defineNuxtModule({
    setup() {
        const resolver = createResolver(import.meta.url);

        extendPages(pages => {
            const postFile = resolver.resolve('../pages/posts.vue');
            pages.find(p => p.path === '/posts')?.children?.push(
                { path: 'fid/:fid(\\d+)', name: 'post/fid', file: postFile },
                { path: 'tid/:tid(\\d+)', name: 'post/tid', file: postFile },
                { path: 'pid/:pid(\\d+)', name: 'post/pid', file: postFile },
                { path: 'spid/:spid(\\d+)', name: 'post/spid', file: postFile },
                { path: ':pathMatch(.*)*', name: 'post/param' }
            );
            pages.push({ path: '/p', redirect: '/posts' });
            const userFile = resolver.resolve('../pages/users.vue');
            pages.find(p => p.path === '/users')?.children?.push(
                { path: 'id/:uid(\\d+)', name: 'user/uid', file: userFile },
                { path: 'name/:name', name: 'user/name', file: userFile },
                { path: 'displayName/:displayName', name: 'user/displayName', file: userFile }
            );
            pages.push({ path: '/u', redirect: '/users' });
        });
    }
});
