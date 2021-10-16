import { createRouter, createWebHistory } from 'vue-router';
import Index from '@/views/Index.vue';

export default createRouter({
    history: createWebHistory(process.env.VUE_APP_PUBLIC_PATH),
    routes: [
        { path: '/', component: Index },
        { path: '/status', component: async () => import('@/views/Status.vue') }
    ]
});
