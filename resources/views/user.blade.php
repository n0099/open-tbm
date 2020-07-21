@extends('layout')
@include('module.vue.scrollList')
@include('module.vue.tiebaSelectUser')

@section('title', '用户查询')

@section('style')
    <style>
        .loading-list-placeholder .post-item-placeholder {
            height: 480px;
        }
        .post-item-placeholder {
            background-image: url({{ asset('img/tombstone-post-list.svg') }});
            background-size: 100%;
        }

        .user-item-enter-active, .user-item-leave-active {
            transition: opacity .3s;
        }
        .user-item-enter, .user-item-leave-to {
            opacity: 0;
        }
    </style>
@endsection

@section('container')
    @verbatim
        <template id="user-list-template">
            <div>
                <div class="reply-list-previous-page p-2 row align-items-center">
                    <div class="col align-middle"><hr /></div>
                    <div v-for="page in [usersData.pages]" class="w-auto">
                        <div class="p-2 badge badge-light">
                            <a v-if="page.currentPage > 1" class="badge badge-primary" :href="previousPageUrl">上一页</a>
                            <p class="h4" v-text="`第 ${page.currentPage} 页`"></p>
                            <span class="small" v-text="`第 ${page.firstItem}~${page.firstItem + page.currentItems - 1} 条`"></span>
                        </div>
                    </div>
                    <div class="col align-middle"><hr /></div>
                </div>
                <scroll-list :items="usersData.users"
                             item-dynamic-dimensions :item-initial-dimensions="{ height: '20em' }"
                             :item-min-display-num="15" item-transition-name="user-item"
                             :item-outer-attrs="{ id: { type: 'eval', value: 'item.uid' } }"
                             :item-inner-attrs="{ class: { type: 'string', value: 'row' } }">
                    <template v-slot="slotProps">
                        <template v-for="user in [slotProps.item]">
                            <div class="col-3">
                                <img class="lazyload d-block mx-auto badge badge-light" width="120px" height="120px"
                                     :data-src="$data.$$getTiebaUserAvatarUrl(user.avatarUrl)" />
                            </div>
                            <div class="col">
                                <p>UID：{{ user.uid }}</p>
                                <p v-if="user.displayName != null">覆盖ID：{{ user.displayName }}</p>
                                <p v-if="user.name != null">用户名：{{ user.name }}</p>
                                <p>性别：{{ getUserGender(user.gender) }}</p>
                                <p v-if="user.fansNickname != null">粉丝头衔：{{ user.fansNickname }}</p>
                            </div>
                            <div v-if="slotProps.itemIndex != usersData.users.length - 1" class="w-100"><hr /></div>
                        </template>
                    </template>
                </scroll-list>
                <div v-if="! loadingNewUsers && ! isLastPageInPages" class="reply-list-next-page p-4">
                    <div class="row align-items-center">
                        <div class="col"><hr /></div>
                        <div class="w-auto" v-for="page in [usersData.pages]">
                            <button @click="queryNewPage(page.currentPage + 1)"
                                    type="button" class="btn btn-secondary">
                                <span class="h4">下一页</span>
                            </button>
                        </div>
                        <div class="col"><hr /></div>
                    </div>
                </div>
            </div>
        </template>
        <template id="user-query-form-template">
            <form @submit.prevent="submitQueryForm(formData)" class="mt-3">
                <div class="form-inline form-group form-row">
                    <select-user v-model="selectUser"></select-user>
                    <label class="col-2 col-form-label" for="queryGender">性别</label>
                    <select v-model="formData.query.gender"
                            id="queryGender" class="form-control col-3">
                        <option value="default">不限</option>
                        <option value="0">未指定（显示为男）</option>
                        <option value="1">男 ♂</option>
                        <option value="2">女 ♀</option>
                    </select>
                </div>
                <button type="submit" class="ml-auto btn btn-primary">查询</button>
            </form>
        </template>
        <template id="user-list-pages-template">
            <div>
                <user-list v-for="(usersData, currentListPage) in userPages"
                           :key="`page${currentListPage + 1}@${JSON.stringify(_.merge({}, $route.params, $route.query))}`"
                           :users-data="usersData"
                           :loading-new-users="loadingNewUsers"
                           :is-last-page-in-pages="currentListPage == userPages.length - 1"></user-list>
                <loading-list-placeholder v-if="loadingNewUsers"></loading-list-placeholder>
            </div>
        </template>
        <template id="error-404-placeholder-template">
            <div class="text-center" style="font-size: 8em">
                <hr />404
            </div>
        </template>
        <div id="user-query">
            <user-query-form></user-query-form>
            <router-view></router-view>
            <error-404-placeholder v-show="showError404Placeholder"></error-404-placeholder>
            <div v-show="showFirstLoadingPlaceholder" id="first-loading-placeholder">
                <!-- use div instead of template to display div dom before vue loaded -->
                <div id="loading-list-placeholder-template">
                    <div class="loading-list-placeholder row align-items-center">
                        <div class="col"><hr /></div>
                        <div class="w-auto">
                            <div class="loading-icon mx-auto"></div>
                        </div>
                        <div class="col"><hr /></div>
                        <div class="w-100"></div>
                        <div class="col">
                            <div class="post-item-placeholder"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endverbatim
@endsection

@section('script')
    <script>
        'use strict';
        $$initialNavBar('user');

        const loadingListPlaceholderComponent = Vue.component('loading-list-placeholder', {
            template: '#loading-list-placeholder-template'
        });

        const error404PlaceholderComponent = Vue.component('error-404-placeholder', {
            template: '#error-404-placeholder-template'
        });

        const userListComponent = Vue.component('user-list', {
            template: '#user-list-template',
            props: { // received from user list pages component
                usersData: Object,
                loadingNewUsers: Boolean,
                isLastPageInPages: Boolean
            },
            data () {
                return {
                    $$getTiebaUserAvatarUrl,
                    displayingItemsID: []
                };
            },
            computed: {
                previousPageUrl () { // computed function will caching attr to ensure each list component's url won't be updated after page param change
                    // generate an new absolute url with previous page params which based on current route path
                    let urlWithNewPage = this.$route.fullPath.replace(`/page/${this.$route.params.page}`, `/page/${this.$route.params.page - 1}`);
                    return `${$$baseUrlDir}${urlWithNewPage}`;
                }
            },
            mounted () {

            },
            methods: {
                getUserGender (gender) {
                    let gendersList = {
                        0: '未指定（显示为男）',
                        1: '男 ♂',
                        2: '女 ♀'
                    };
                    return gendersList[gender];
                },
                queryNewPage (pageNum, nextPageButtonDOM) {

                }
            }
        });

        const userQueryFormComponent = Vue.component('user-query-form', {
            template: '#user-query-form-template',
            props: {

            },
            data () {
                return {
                    formData: { query: {}, param: {} },
                    selectUser: {}
                };
            },
            computed: {

            },
            watch: {
                selectUser (selectUser) {
                    this.$data.formData.param = selectUser.params;
                }
            },
            created () {
                let queryCustomParams = _.cloneDeep(this.$route.query);
                let queryPathParams = _.omit(_.cloneDeep(this.$route.params), 'pathMatch'); // prevent store pathMatch property into params due to https://github.com/vuejs/vue-router/issues/2503
                this.$data.formData = { query: queryCustomParams, param: queryPathParams };
            },
            beforeMount () {
                this.$data.selectUser.selectBy = this.$route.name;
                this.$data.selectUser.params = this.$route.params;
            },
            methods: {
                submitQueryForm (formData) {
                    formData = _.mapValues(formData, (i) => _.omitBy(i, _.isEmpty)); // deep clone, remove falsy values
                    this.$router.push({
                        name: _.isEmpty(formData.param) ? 'emptyParam' : this.$data.selectUser.selectBy,
                        query: formData.query,
                        params: formData.param
                    });
                }
            }
        });

        const userListPagesComponent = Vue.component('user-list-pages', {
            template: '#user-list-pages-template',
            data () {
                return {
                    userPages: [],
                    loadingNewUsers: false
                };
            },
            watch: {
                loadingNewUsers (loadingNewUsers) {
                    if (loadingNewUsers) {
                        this.$parent.showError404Placeholder = false;
                        this.$parent.showFirstLoadingPlaceholder = false;
                    }
                }
            },
            mounted () {
                this.queryUsersData(this.$route);
            },
            methods: {
                queryUsersData (route) {
                    let ajaxStartTime = Date.now();
                    let ajaxQueryString = _.merge({}, route.params, route.query); // deep clone
                    if (_.isEmpty(ajaxQueryString)) {
                        new Noty({ timeout: 3000, type: 'info', text: '请输入用户查询参数'}).show();
                        this.$parent.showFirstLoadingPlaceholder = false;
                        return;
                    }
                    this.$data.loadingNewUsers = true;
                    $$reCAPTCHACheck().then((token) => {
                        ajaxQueryString = $.param(_.merge(ajaxQueryString, token));
                        $.getJSON(`${$$baseUrl}/api/usersQuery`, ajaxQueryString)
                            .done((ajaxData) => {
                                this.$data.userPages = [ajaxData];
                                new Noty({ timeout: 3000, type: 'success', text: `已加载第${ajaxData.pages.currentPage}页 ${ajaxData.pages.currentItems}条记录 耗时${Date.now() - ajaxStartTime}ms`}).show();
                            })
                            .fail((jqXHR) => {
                                this.$data.userPages = [];
                                this.$parent.showError404Placeholder = true;
                            })
                            .always(() => {
                                this.$data.loadingNewUsers = false
                            });
                    });
                }
            },
            beforeRouteUpdate (to, from, next) {
                this.queryUsersData(to);
                next();
            }
        });
        const userQueryVue = new Vue({
            el: '#user-query',
            data () {
                return {
                    showFirstLoadingPlaceholder: true, // show by initially
                    showError404Placeholder: false
                };
            },
            router: new VueRouter({
                mode: 'history',
                base: `${$$baseUrlDir}/`,
                routes: [
                    {
                        name: 'emptyParam',
                        path: '/user',
                        component: userListPagesComponent,
                        children: [
                            { name: 'emptyParam+p', path: 'page/:page' },
                            { name: 'uid', path: 'id/:uid', children: [{ name:'uid+p', path: 'page/:page' }] },
                            { name: 'name', path: 'n/:name', children: [{ name:'name+p', path: 'page/:page' }] },
                            { name: 'displayName', path: 'dn/:displayName', children: [{ name:'displayName+p', path: 'page/:page' }] }
                        ]
                    }
                ]
            })
        });
    </script>
@endsection
