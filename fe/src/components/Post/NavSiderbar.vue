<template>
    <a-menu v-model="selectedThread" @click="selectThread" :open-keys.sync="expandedPages" :force-sub-menu-render="true" :inline-indent="16" mode="inline" class="posts-nav">
        <template v-for="posts in postPages">
            <a-sub-menu v-for="page in [posts.pages.currentPage]" @title-click="selectPage" :title="`第${page}页`" :key="`page-${page}`">
                <a-menu-item v-for="thread in posts.threads" v-scroll-to-post="firstPostInView.page === page && firstPostInView.tid === thread.tid"
                             :key="`page-${page}_t${thread.tid}`" :title="thread.title">
                    {{ thread.title }}
                    <div class="d-block btn-group" role="group">
                        <a @click="navigate(page, null, reply.pid)"
                           v-for="reply in thread.replies" v-scroll-to-post="firstPostInView.page === page && firstPostInView.pid === reply.pid"
                           :class="{
                                    'btn': true,
                                    'btn-info': reply.pid === firstPostInView.pid,
                                    'btn-light': reply.pid !== firstPostInView.pid
                               }" href="#!">
                            {{ reply.floor }}L
                        </a>
                    </div>
                    <hr />
                </a-menu-item>
            </a-sub-menu>
        </template>
    </a-menu>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    setup() {

    }
});

const postsNavComponent = Vue.component('posts-nav', {
    template: '#posts-nav-template',
    props: {
        postPages: { type: Array, required: true }
    },
    data () {
        return {
            firstPostInView: window.$sharedData.firstPostInView,
            expandedPages: [],
            selectedThread: []
        };
    },
    directives: {
        'scroll-to-post' (el, binding, vnode) {
            if (binding.value !== binding.oldValue
                && binding.value === true
                && $('.posts-nav').css('display') !== 'none') { // don't scroll when <posts-nav> is collapsed
                $(el).parents('.ant-menu-sub').css('height', 'unset'); // force expand parent <a-sub-menu> to skip .ant-motion-collapse-legacy-active animation while page's first load
                el.scrollIntoViewIfNeeded(); // fixme: should only scroll within <posts-nav> instead of whole window
            }
        }
    },
    watch: {
        postPages (to, from) {
            this.$data.expandedPages = _.map(to, (i) => `page-${i.pages.currentPage}`);
        },
        firstPostInView: {
            handler: function (to, from) {
                this.$data.selectedThread = [`page-${to.page}_t${to.tid}`];
            },
            deep: true
        }
    },
    methods: {
        navigate (page, tid = null, pid = null) {
            this.$router.replace({ hash: `#${pid || (tid !== null ? 't' + tid : null)}`, params: { ...this.$route.params, 0: this.$route.params.pathMatch, page } }); // [vue-router] missing param for named route "param+p": Expected "0" to be defined
        },
        selectThread ({ domEvent, key }) {
            if (domEvent.target.tagName !== 'A') { // omit reply link click events
                let postPosition = /page-(\d+)_t(\d+)/.exec(key);
                this.navigate(postPosition[1], postPosition[2]);
            }
        },
        selectPage ({ key }) { // fixme: titleClick event on <a-menu> not triggered
            this.navigate(/page-(\d+)/.exec(key)[1]);
        }
    }
});
</script>

<style scoped>
.posts-nav .ant-menu-item {
    height: auto !important; /* to show reply nav buttons under thread menu items */
    padding: 0 5px 0 28px !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    white-space: normal !important;
}
.posts-nav .ant-menu-item hr {
    margin: 7px 0 0 0;
}

.posts-nav {
    padding: 0 10px 0 0;
    overflow: hidden;
    border-right: 1px solid #ededed;
}
.posts-nav:hover {
    padding: 0;
    overflow-y: auto;
}
@media (max-width: 1200px) {
    .posts-nav[aria-expanded=true] {
        display: block !important;
        position: sticky;
        top: 0;
        left: 0;
        width: fit-content;
        max-width: 35%;
    }
}
</style>
