import { defineStore } from 'pinia';
import viewer from 'v-viewer';

export const useViewerStore = defineStore('viewer', () => {
    const isEnabled = ref(false);
    const enable = () => {
        if (isEnabled.value)
            return;

        useNuxtApp().vueApp.use(viewer, {
            defaultOptions: {
                url: 'data-origin',
                filter: (img: HTMLImageElement) => img.classList.contains('tieba-ugc-image')
            }
        });
        isEnabled.value = true;
    };

    return { enable };
});
