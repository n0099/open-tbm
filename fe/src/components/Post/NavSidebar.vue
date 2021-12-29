<template>
    <Menu v-model="selectedThread" v-model:openKeys="expandedPages" @click="selectThread"
          :forceSubMenuRender="true" :inlineIndent="16" mode="inline"
          :class="{ 'd-none': !postsNavExpanded }" :aria-expanded="postsNavExpanded"
          class="posts-nav col-xl d-xl-block sticky-top">
        <template v-for="posts in postPages">
            <SubMenu v-for="page in [posts.pages.currentPage]" :key="`page-${page}`"
                     @title-click="selectPage" :title="`第${page}页`">
                <MenuItem v-for="thread in posts.threads" :key="`page-${page}_t${thread.tid}`"
                          v-scroll-to-post="firstPostInView.page === page && firstPostInView.tid === thread.tid"
                          :title="thread.title" class="nav-sidebar-thread-item">
                    {{ thread.title }}
                    <div class="d-block btn-group" role="group">
                        <a @click="navigate(page, null, reply.pid)"
                           v-for="reply in thread.replies" :key="reply.pid"
                           v-scroll-to-post="firstPostInView.page === page && firstPostInView.pid === reply.pid"
                           :class="{
                               'btn': true,
                               'btn-info': reply.pid === firstPostInView.pid,
                               'btn-light': reply.pid !== firstPostInView.pid
                           }" href="#!">
                            {{ reply.floor }}L
                        </a>
                    </div>
                    <hr />
                </MenuItem>
            </SubMenu>
        </template>
    </Menu>
    <a @click="postsNavExpanded = !postsNavExpanded"
       class="posts-nav-expanded col col-auto align-items-center d-flex d-xl-none shadow-sm vh-100 sticky-top">
        <!-- https://github.com/FortAwesome/vue-fontawesome/issues/313 -->
        <span v-show="postsNavExpanded"><FontAwesomeIcon icon="angle-left" /></span>
        <span v-show="!postsNavExpanded"><FontAwesomeIcon icon="angle-right" /></span>
    </a>
</template>

<script lang="ts">
import type { ApiPostsQuery } from '@/api/index.d';
import type { PropType } from 'vue';
import { defineComponent, reactive, toRefs, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Menu, MenuItem, SubMenu } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

export default defineComponent({
    components: { FontAwesomeIcon, Menu, MenuItem, SubMenu },
    props: {
        postPages: { type: Array as PropType<ApiPostsQuery[]>, required: true }
    },
    directives: {
        'scroll-to-post'(el, binding) {
            if (binding.value !== binding.oldValue
                && binding.value === true
                && $('.posts-nav').css('display') !== 'none') { // don't scroll when <posts-nav> is collapsed
                // force expand parent <a-sub-menu> to skip .ant-motion-collapse-legacy-active animation while page's first load
                $(el).parents('.ant-menu-sub').css('height', 'unset');
                el.scrollIntoViewIfNeeded(); // fixme: should only scroll within <posts-nav> instead of whole window
            }
        }
    },
    setup(props) {
        const route = useRoute();
        const router = useRouter();
        const state = reactive({
            firstPostInView: window.$sharedData.firstPostInView,
            expandedPages: [],
            selectedThread: [],
            postsNavExpanded: false
        });
        const navigate = (page, tid = null, pid = null) => {
            router.replace({
                hash: `#${pid || (tid !== null ? `t${tid}` : null)}`,
                params: { ...route.params, 0: route.params.pathMatch, page }
            }); // [vue-router] missing param for named route "param+p": Expected "0" to be defined
        };
        const selectThread = ({ domEvent, key }) => {
            if (domEvent.target.tagName !== 'A') { // omit reply link click events
                const postPosition = /page-(\d+)_t(\d+)/.exec(key);
                navigate(postPosition[1], postPosition[2]);
            }
        };
        const selectPage = ({ key }) => { // fixme: titleClick event on <a-menu> not triggered
            navigate(/page-(\d+)/.exec(key)[1]);
        };

        watch(() => props.postPages, (to, from) => {
            state.expandedPages = _.map(to, i => `page-${i.pages.currentPage}`);
        });
        watch(() => state.firstPostInView, (to, from) => {
            state.selectedThread = [`page-${to.page}_t${to.tid}`];
        }, { deep: true });

        return { ...toRefs(state), navigate, selectThread, selectPage };
    }
});
</script>

<style>/* to override styles for dom under another component <MenuItem>, we have to declare in global scope */
.nav-sidebar-thread-item {
    height: auto !important; /* to show reply nav buttons under thread menu items */
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    white-space: normal;
}
.nav-sidebar-thread-item hr {
    margin: .5rem 0 0 0;
}
.nav-sidebar-thread-item .ant-menu-title-content {
    padding-left: .5rem;
}
</style>

<style scoped>
.posts-nav-expanded {
    padding: 2px;
    font-size: 1.3rem;
    background-color: whitesmoke;
}
.posts-nav {
    padding: 0 1.5rem 0 0;
    overflow: hidden;
    max-height: 100vh;
    border-top: 1px solid #f0f0f0;
}
.posts-nav:hover {
    padding: 0;
    overflow-y: auto;
}
@media (max-width: 1200px) {
    .posts-nav[aria-expanded=true] {
        width: fit-content;
        max-width: 35%;
    }
}
</style>
