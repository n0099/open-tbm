@extends('layout')

@section('title', '用户查询')

@section('container')
    <style>
        .user-item-initial {
            height: 20em;
        }
        .user-item-enter-active, .user-item-leave-active {
            transition: opacity .3s;
        }
        .user-item-enter, .user-item-leave-to {
            opacity: 0;
        }
    </style>
    @verbatim
        <template id="users-list-template">
            <div>
                <div class="users-list">
                    <div class="reply-list-previous-page p-2 row align-items-center">
                        <div class="col align-middle"><hr /></div>
                        <div v-for="page in [usersData.pages]" class="w-auto">
                            <div class="p-2 badge badge-light">
                                <a v-if="page.currentPage > 1" class="badge badge-primary" :href="getPreviousPageUrl">上一页</a>
                                <p class="h4" v-text="`第 ${page.currentPage} 页`"></p>
                                <span class="small" v-text="`第 ${page.firstItem}~${page.firstItem + page.currentItems - 1} 条`"></span>
                            </div>
                        </div>
                        <div class="col align-middle"><hr /></div>
                    </div>
                    <scroll-list :items="usersData.users" :items-initial-height="'20em'"
                                 :items-showing-num="5" :item-transition-name="'user-item'"
                                 :items-outer-attrs="{ id: { type: 'eval', value: 'item.uid' } }"
                                 :items-inner-attrs="{ class: { type: 'string', value: 'row' } }">
                        <template v-slot="slotProps">
                            <template v-for="user in [slotProps.item]">
                                <p>{{ user }}</p>
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
                            </template>
                        </template>
                    </scroll-list>
                    <div class="reply-list-next-page p-4">
                        <div class="row align-items-center">
                            <div class="col"><hr /></div>
                            <div class="w-auto" v-for="page in [usersData.pages]">
                                <span v-if="page.currentPage == page.lastPage" class="h4">已经到底了~</span><!-- TODO: fix last page logical-->
                                <button v-else @click="loadNewThreadsPage($event.currentTarget, page.currentPage + 1)" type="button" class="btn btn-secondary">
                                    <span class="h4">下一页</span>
                                </button>
                            </div>
                            <div class="col"><hr /></div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template id="users-list-pages-template">
            <div>
                <users-list v-for="(usersData, currentUserPage) in usersPages"
                            :key="`page${currentUserPage + 1}@${JSON.stringify(_.merge({}, $route.params, $route.query))}`"
                            :users-data="usersData"></users-list>
            </div>
        </template>
        <div id="users-list">
            <router-view></router-view>
        </div>
        @endverbatim
@endsection

@section('script-after-container')
    <script>
        'use strict';
        $$initialNavBar('user');

        const usersListComponent = Vue.component('users-list', {
            template: '#users-list-template',
            props: { // received from parent component
                usersData: Object
            },
            data: function () {
                return {
                    $$getTiebaUserAvatarUrl,
                    displayingItemsID: []
                };
            },
            computed: {
                getPreviousPageUrl: function () { // computed function will caching attr to ensure each posts-list's url will not updated after page param change
                    // generate an new absolute url with previous page params which based on current route path
                    let urlWithNewPage = this.$route.fullPath.replace(`/page/${this.$route.params.page}`, `/page/${this.$route.params.page - 1}`);
                    return `${$$baseUrlDir}${urlWithNewPage}`;
                }
            },
            mounted: function () {

            },
            methods: {
                getUserGender: function (gender) {
                    let gendersList = {
                        0: '未指定（显示为男）',
                        1: '男 ♂',
                        2: '女 ♀'
                    };
                    return Reflect.get(gendersList, gender);
                }
            }
        });

        const usersListPagesComponent = Vue.component('users-list-pages', {
            template: '#users-list-pages-template',
            data: function () {
                return {
                    usersPages: []
                };
            },
            mounted: function () {
                $$reCAPTCHACheck().then((token) => {
                    $.getJSON(`${$$baseUrl}/api/usersQuery`, $.param(_.merge({ gender: 2 }, token)))
                        .done((jsonData) => {
                            this.$data.usersPages.push(jsonData);
                        })
                        .fail((jqXHR) => {

                        });
                });
            },
            methods: {

            }
        });

        let usersListVue = new Vue({
            el: '#users-list',
            router: new VueRouter({
                mode: 'history',
                base: `${$$baseUrlDir}/`,
                routes: [
                    {
                        name: 'usersQuery',
                        path: '/user',
                        component: usersListPagesComponent,
                        children: [
                            { name: 'usersQuery+p', path: 'page/:page' },
                            { name: 'uid', path: 'uid/:uid', children: [{ name:'uid+p', path: 'page/:page' }] }
                        ]
                    }
                ]
            })
        });
    </script>
@endsection