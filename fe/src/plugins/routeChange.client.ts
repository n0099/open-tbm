import nprogress from 'nprogress';
import _ from 'lodash';

export default defineNuxtPlugin(() => {
    const isRouteChanging = useState('isRouteChanging', () => false);
    const start = () => {
        if (import.meta.client) 
            nprogress.start();
        isRouteChanging.value = true;
        window.setTimeout(end, 10000);
    };
    let timeoutId = 0;
    const end = () => {
        clearTimeout(timeoutId);
        if (import.meta.client) 
            nprogress.done();
        isRouteChanging.value = false;
    };

    const router = useRouter();
    router.beforeEach((to, from) => {
        const getPathFirstDirectory = (path: string) => {
            const secondSlashIndex = path.indexOf('/', 1);
            return secondSlashIndex === -1 ? path : path.substring(0, secondSlashIndex);
        };
        if (getPathFirstDirectory(to.path) === getPathFirstDirectory(from.path))
            return;
        timeoutId = window.setTimeout(start, 100);
    });
    router.afterEach(end);
    router.onError((error) => {
        end();
        notyShow('error', error instanceof Error
            ? `${error.name}<br />${error.message}`
            : JSON.stringify(error));
    });
});
