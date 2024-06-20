import _ from 'lodash';

export default defineNuxtPlugin(() => {
    const { start, end } = useRouteUpdatingStore();

    const router = useRouter();
    router.beforeEach((to, from) => {
        if (isPathsFirstDirectorySame(to.path, from.path))
            return;
        start();
    });
    router.afterEach(end);
    router.onError((error) => {
        end();
        notyShow('error', error instanceof Error
            ? `${error.name}<br />${error.message}`
            : JSON.stringify(error));
    });
});
