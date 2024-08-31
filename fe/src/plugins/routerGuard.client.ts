export default defineNuxtPlugin(() => {
    const { start, stop } = useRouteUpdatingStore();

    const router = useRouter();
    router.beforeEach((to, from) => {
        if (isPathsFirstDirectorySame(to.path, from.path))
            return;
        start();
    });
    router.afterEach(stop);
    router.onError(error => {
        stop();
        notyShow('error', error instanceof Error
            ? `${error.name}<br>${error.message}`
            : JSON.stringify(error));
    });
});
