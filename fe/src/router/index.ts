import { createRouter, createWebHistory } from 'vue-router';
import Index from '@/views/Index.vue';

export default createRouter({
    history: createWebHistory(process.env.BASE_URL),
    routes: [
        { path: '/', component: Index }
    ]
});
