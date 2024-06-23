import { addComponent, defineNuxtModule } from '@nuxt/kit';

export default defineNuxtModule({
    async setup() {
        await addComponent({
            filePath: '@fortawesome/vue-fontawesome',
            export: 'FontAwesomeIcon',
            name: 'FontAwesomeIcon'
        });
    }
});
