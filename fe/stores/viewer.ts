import { app } from '~/main';
import viewer from 'v-viewer';
import { defineStore } from 'pinia';

export const useViewerStore = defineStore('viewer', () => {
    const isEnabled = ref(false);
    const enable = () => {
        if (isEnabled.value)
            return;

        app.use(viewer, {
            defaultOptions: {
                url: 'data-origin',
                filter: (img: HTMLImageElement) => img.classList.contains('tieba-ugc-image')
            }
        });
        isEnabled.value = true;
    };

    return { enable };
});
