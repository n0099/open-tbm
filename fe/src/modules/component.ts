import { addComponent, defineNuxtModule } from '@nuxt/kit';

export default defineNuxtModule({
    setup() {
        addComponent({
            filePath: '@fortawesome/vue-fontawesome',
            export: 'FontAwesomeIcon',
            name: 'FontAwesomeIcon'
        });
    }
});
