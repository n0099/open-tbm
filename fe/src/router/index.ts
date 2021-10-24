import NProgress from 'nprogress';
import type { Component } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import Index from '@/views/Index.vue';

const lazyLoadRouteView = async (component: Promise<Component>) => {
    NProgress.start();
    const loadingBlocksDom = document.getElementById('loadingBlocksRouteChange');
    const [containerDom] = document.getElementsByClassName('container');
    loadingBlocksDom?.classList.remove('d-none');
    containerDom.classList.add('invisible');
    component.finally(() => {
        NProgress.done();
        loadingBlocksDom?.classList.add('d-none');
        containerDom.classList.remove('invisible');
    });
    return component;
};

export default createRouter({
    history: createWebHistory(process.env.VUE_APP_PUBLIC_PATH),
    routes: [
        { path: '/', name: 'index', component: Index },
        { path: '/post', name: 'post' },
        { path: '/user', name: 'user' },
        { path: '/status', name: 'status', component: async () => lazyLoadRouteView(import('@/views/Status.vue')) },
        { path: '/stats', name: 'stats', component: async () => lazyLoadRouteView(import('@/views/Stats.vue')) },
        { path: '/bilibiliVote', name: 'bilibiliVote', component: async () => lazyLoadRouteView(import('@/views/BilibiliVote.vue')) }
    ],
    linkActiveClass: 'active'
});
