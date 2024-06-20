import _ from 'lodash';

export default defineNuxtPlugin(() => {
    const { start, end } = useRouteUpdatingStore();

    const router = useRouter();
    router.beforeEach((to, from) => {
        const getPathFirstDirectory = (path: string) => {
            const secondSlashIndex = path.indexOf('/', 1);
            return secondSlashIndex === -1 ? path : path.substring(0, secondSlashIndex);
        };
        if (getPathFirstDirectory(to.path) === getPathFirstDirectory(from.path))
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
