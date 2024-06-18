import { createResolver, defineNuxtModule, extendPages } from '@nuxt/kit';

export default defineNuxtModule({
    setup() {
        const resolver = createResolver(import.meta.url);

        extendPages(pages => {
            const postFile = resolver.resolve('../pages/posts.vue');
            pages.find(p => p.path === '/posts')?.children?.push(
                { path: 'fid/:fid(\\d+)', name: 'posts/fid', file: postFile },
                { path: 'tid/:tid(\\d+)', name: 'posts/tid', file: postFile },
                { path: 'pid/:pid(\\d+)', name: 'posts/pid', file: postFile },
                { path: 'spid/:spid(\\d+)', name: 'posts/spid', file: postFile },
                { path: ':pathMatch(.*)*', name: 'posts/param' }
            );
            pages.push({ path: '/p', redirect: '/posts' });
            const userFile = resolver.resolve('../pages/users.vue');
            pages.find(p => p.path === '/users')?.children?.push(
                { path: 'id/:uid(\\d+)', name: 'users/uid', file: userFile },
                { path: 'name/:name', name: 'users/name', file: userFile },
                { path: 'displayName/:displayName', name: 'users/displayName', file: userFile }
            );
            pages.push({ path: '/u', redirect: '/users' });
        });
    }
});
