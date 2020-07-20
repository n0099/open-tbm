@extends('layout')
@include('module.bootstrapCallout')
@include('module.tiebaPostContentElement')
@include('module.vue.scrollList')
@include('module.vue.tiebaSelectUser')

@section('title', '贴子查询')

@section('style')
    <style>
        .bs-callout {
            padding: .625em !important;
            margin: .625em 0 !important;
        }

        .loading-list-placeholder .post-item-placeholder {
            height: 480px;
        }
        .post-item-placeholder {
            background-image: url({{ asset('img/tombstone-post-list.svg') }});
            background-size: 100%;
        }

        .posts-nav-btn {
            opacity: 0.5;
            position: fixed;
            bottom: 0;
            right: 0;
            z-index: 1022;
        }
        .posts-nav {
            position: fixed;
            bottom: 0;
            right: 0;
            overflow: auto;
            width: 40%;
            height: 100%;
            z-index: 1021;
        }
        .posts-nav-enter-active, .posts-nav-leave-active {
            transition: display 3s;
        }
        .posts-nav-enter, .posts-nav-leave-to {
            display: none;
        }
        .posts-nav a {
            padding: .25em .5em .25em .5em;
            margin: .25em;
        }
        .posts-nav a:hover {
            border-color: #17a2b8;
        }
        .posts-nav-page-link {
            width: 100%;
        }
        .posts-nav-thread {
            margin-left: 10%;
            width: 90%;
        }
        .posts-nav-thread-link {
            width: 90%;
        }

        .card-body {
            padding: 0.5em;
        }

        .thread-item {
            margin-top: 1em;
        }
        .thread-item-enter-active, .thread-item-leave-active {
            transition: opacity .3s;
        }
        .thread-item-enter, .thread-item-leave-to {
            opacity: 0;
        }
        .thread-title {
            background-color: #F2F2F2;
        }

        .reply-title {
            top: 72px;
            margin-top: .625em;
            border-top: 1px solid #ededed;
            border-bottom: 0;
            background: linear-gradient(rgba(237,237,237,1), rgba(237,237,237,.1));
            z-index: 1019;
        }
        .reply-info {
            margin: 0 !important;
            border-top: 0 !important;
        }
        .reply-banner {
            padding-left: 0;
            padding-right: .5em;
        }
        .reply-body {
            width: 0; /* let reply-body show abreast with reply-banner */
            padding-left: .5em;
            padding-right: .5em;
        }
        .reply-user-info {
            z-index: 1018;
            top: 8em;
            padding: .25em;
            font-size: 1em;
            line-height: 140%;
        }
        .reply-user-info a:link {
            text-decoration: none;
        }

        .sub-reply-group {
            margin: 0 0 .25em .5em !important;
            padding: .25em !important;
        }
        .sub-reply-item {
            padding: .125em .125em .125em .625em;
        }
        .sub-reply-item > * {
            padding: .25em;
        }
        .sub-reply-user-info {
            font-size: 0.9em;
        }
    </style>
@endsection

@section('container')
    @verbatim
        <template id="post-list-template">
            <div :data-page="postsData.pages.currentPage" class="post-list">
                <div class="reply-list-previous-page p-2 row align-items-center">
                    <div class="col"><hr /></div>
                    <div v-for="page in [postsData.pages]" class="w-auto">
                        <div class="p-2 badge badge-light">
                            <a v-if="page.currentPage > 1"
                               class="badge badge-primary" :href="previousPageUrl">上一页</a>
                            <p class="h4" v-text="`第 ${page.currentPage} 页`"></p>
                            <p class="small" v-text="`第 ${page.firstItem}~${page.firstItem + page.currentItems - 1} 条`"></p>
                            <span class="h5" v-text="`${postsData.forum.name}吧`"></span>
                        </div>
                    </div>
                    <div class="col"><hr /></div>
                </div>
                <scroll-list :items="postsData.threads"
                             item-dynamic-dimensions :item-initial-dimensions="{ height: '500em' }"
                             :item-min-display-num="3" item-transition-name="thread-item"
                             :item-inner-attrs="{
                                'data-title': { type: 'eval', value: 'item.title'},
                                class: { type: 'string', value: 'thread-item card' }
                             }"
                             item-placeholder-class="post-item-placeholder"
                             ref="threadItemsScrollList">
                    <template v-slot="slotProps">
                        <template v-for="thread in [slotProps.item]">
                            <div class="thread-title shadow-sm card-header sticky-top">
                                <span v-if="thread.stickyType == 'membertop'" class="badge badge-warning">会员置顶</span>
                                <span v-if="thread.stickyType == 'top'" class="badge badge-primary">置顶</span>
                                <span v-if="thread.isGood" class="badge badge-danger">精品</span>
                                <span v-if="thread.topicType == 'text'" class="badge badge-danger">文本话题</span>
                                <span v-if="thread.topicType == 'text'" class="badge badge-danger">文本话题</span><!-- TODO: fill unknown picture topic thread type -->
                                <h6 class="d-inline">{{ thread.title }}</h6>
                                <div class="float-right badge badge-light">
                                    <router-link :to="{ name: 'tid', params: { tid: thread.tid.toString() } }"
                                                 class="thread-list-show-only badge badge-pill badge-light">只看此贴</router-link>
                                    <a class="badge badge-pill badge-light" :href="$data.$$getTiebaPostLink(thread.tid)" target="_blank">
                                        <i class="fas fa-link"></i>
                                    </a>
                                    <template v-for="latestReplier in [getUserData(thread.latestReplierUid)]">
                                        <a class="badge badge-pill badge-light" href="#!"
                                           :data-tippy-content="`<h6>ID：${thread.tid}</h6><hr />
                                                最后回复人：${latestReplier.displayName == null
                                                    ? latestReplier.name
                                                    : latestReplier.displayName + '（' + latestReplier.name + '）'}<br />
                                                最后回复时间：${thread.latestReplyTime}<br />
                                                收录时间：${thread.created_at}<br />
                                                最后更新：${thread.updated_at}`">
                                            <i class="fas fa-info"></i>
                                        </a>
                                    </template>
                                    <span class="badge badge-pill badge-success" :data-tippy-content="thread.postTime">{{ moment(thread.postTime).fromNow() }}</span>
                                </div>
                                <div>
                                    <span data-tippy-content="回复量" class="badge badge-info">
                                        <i class="far fa-comment-alt"></i> {{ thread.replyNum }}
                                    </span>
                                    <span data-tippy-content="阅读量" class="badge badge-info">
                                        <i class="far fa-eye"></i> {{ thread.viewNum }}
                                    </span>
                                    <span data-tippy-content="分享次数" class="badge badge-info">
                                        <i class="fas fa-share-alt"></i> {{ thread.shareNum }}
                                    </span>
                                    <span v-if="thread.agreeInfo != null" data-tippy-content="总赞踩量" class="badge badge-info">
                                        <i class="far fa-thumbs-up"></i>{{ thread.agreeInfo.agree_num }}
                                        <i class="far fa-thumbs-down"></i>{{ thread.agreeInfo.disagree_num }}
                                    </span>
                                    <span v-if="thread.zanInfo != null" class="badge badge-info"
                                          :data-tippy-content="`
                                            点赞量：${thread.zanInfo.num}<br />
                                            最后点赞时间：${thread.zanInfo.last_time}<br />
                                            近期点赞用户：${thread.zanInfo.user_id_list}<br />`">
                                        <i class="far fa-thumbs-up"></i> 旧版客户端赞
                                    </span>
                                    <span data-tippy-content="发贴位置" class="badge badge-info">
                                        <i class="fas fa-location-arrow"></i> {{ thread.locationInfo }}
                                    </span>
                                </div>
                            </div>
                            <div v-for="reply in thread.replies" :key="reply.pid"
                                 v-observe-visibility="{ callback: $parent.replyItemObserveEvent, throttle: 500 }"
                                 :id="reply.pid" class="reply-item">
                                <div class="reply-title sticky-top card-header">
                                    <div class="d-inline h5">
                                        <span class="badge badge-info">{{ reply.floor }}楼</span>
                                        <span v-if="reply.subReplyNum > 0" class="badge badge-info">
                                            {{ reply.subReplyNum }}条<i class="far fa-comment-dots"></i>
                                        </span>
                                        <!-- TODO: implement these reply's property
                                        <span>fold:{{ reply.isFold }}</span>
                                        <span>{{ reply.agreeInfo }}</span>
                                        <span>{{ reply.signInfo }}</span>
                                        <span>{{ reply.tailInfo }}</span>
                                        <span>{{ reply.agreeInfo }}</span>
                                        <span>{{ reply.signInfo }}</span>
                                        <span>{{ reply.tailInfo }}</span>
                                        -->
                                    </div>
                                    <div class="float-right badge badge-light">
                                        <router-link :to="{ name: 'pid', params: { pid: reply.pid.toString() } }"
                                                     class="reply-list-show-only badge badge-pill badge-light">只看此楼</router-link>
                                        <a class="badge badge-pill badge-light" :href="$data.$$getTiebaPostLink(reply.tid, reply.pid)" target="_blank">
                                            <i class="fas fa-link"></i>
                                        </a>
                                        <a class="badge badge-pill badge-light" href="#!"
                                           :data-tippy-content="`
                                                <h6>ID：${reply.pid}</h6><hr />
                                                收录时间：${reply.created_at}<br />
                                                最后更新：${reply.updated_at}`">
                                            <i class="fas fa-info"></i>
                                        </a>
                                        <span class="badge badge-pill badge-primary" :data-tippy-content="reply.postTime">{{ moment(reply.postTime).fromNow() }}</span>
                                    </div>
                                </div>
                                <div class="reply-info shadow-sm row bs-callout bs-callout-info">
                                    <template v-for="author in [getUserData(reply.authorUid)]">
                                        <div class="reply-banner col-md-auto text-center">
                                            <div class="reply-user-info col sticky-top shadow-sm badge badge-light">
                                                <a class="d-block" :href="$data.$$getTiebaUserLink(author.name)" target="_blank">
                                                    <img class="lazyload d-block mx-auto badge badge-light" width="90px" height="90px"
                                                         :data-src="$data.$$getTiebaUserAvatarUrl(author.avatarUrl)" />
                                                    <span>
                                                        {{ author.displayName }}
                                                        <br v-if="author.displayName != null" />
                                                        {{ author.name }}
                                                    </span>
                                                </a>
                                                <div v-if="author.uid == getUserData(thread.authorUid).uid" class="badge badge-pill badge-success">楼主</div>
                                                <div v-if="reply.authorManagerType != null">
                                                    <span v-if="reply.authorManagerType == 'manager'" class="badge badge-danger">吧主</span>
                                                    <span v-else-if="reply.authorManagerType == 'assist'" class="badge badge-info">小吧</span>
                                                    <span v-else-if="reply.authorManagerType == 'voiceadmin'" class="badge badge-info">语音小编</span>
                                                </div>
                                                <div class="badge badge-pill badge-primary">Lv{{ reply.authorExpGrade }}</div>
                                            </div>
                                        </div>
                                    </template>
                                    <div class="reply-body col border-left">
                                        <div v-html="reply.content" class="card-body p-3"></div>
                                        <template v-if="reply.subReplies.length > 0">
                                            <div v-for="subReplyGroup in reply.subReplies" :key="`${reply.pid}-${subReplyGroup[0].spid}`"
                                                 class="sub-reply-group card bs-callout bs-callout-success">
                                                <ul class="list-group list-group-flush">
                                                    <li v-for="(subReply, subReplyIndex) in subReplyGroup" :key="subReply.spid"
                                                        @mouseenter="hoveringSubReplyItem = subReply.spid"
                                                        @mouseleave="hoveringSubReplyItem = null"
                                                        class="sub-reply-item list-group-item">
                                                        <template v-for="author in [getUserData(subReply.authorUid)]">
                                                            <a v-if="subReplyGroup[subReplyIndex - 1] == undefined"
                                                               class="sub-reply-user-info badge badge-light"
                                                               :href="$data.$$getTiebaUserLink(author.name)" target="_blank">
                                                                <img class="lazyload" width="25px" height="25px"
                                                                     :data-src="$data.$$getTiebaUserAvatarUrl(author.avatarUrl)" />
                                                                <span v-if="author.displayName == null">{{ author.name }}</span>
                                                                <span v-else>{{ author.displayName }}（{{ author.name }}）</span>
                                                                <div class="btn-group" role="group">
                                                                    <button v-if="author.uid == getUserData(thread.authorUid).uid" type="button" class="badge btn btn-success">楼主</button>
                                                                    <button v-else-if="author.uid == getUserData(reply.authorUid).uid" type="button" class="badge btn btn-info">层主</button>
                                                                    <button v-if="subReply.authorManagerType == 'manager'" type="button" class="badge btn btn-danger">吧主</button>
                                                                    <button v-else-if="subReply.authorManagerType == 'assist'" type="button" class="badge btn btn-info">小吧</button>
                                                                    <button v-else-if="subReply.authorManagerType == 'voiceadmin'" type="button" class="badge btn btn-info">语音小编</button>
                                                                    <button type="button" class="badge btn btn-primary">Lv{{ subReply.authorExpGrade }}</button>
                                                                </div>
                                                            </a>
                                                            <div class="float-right badge badge-light">
                                                                <div v-show="hoveringSubReplyItem == subReply.spid"
                                                                     :class="{ 'd-inline': hoveringSubReplyItem == subReply.spid }">
                                                                    <a class="badge badge-pill badge-light"
                                                                       :href="$data.$$getTiebaPostLink(subReply.tid, null, subReply.spid)" target="_blank">
                                                                        <i class="fas fa-link"></i>
                                                                    </a>
                                                                    <a class="badge badge-pill badge-light" href="#!"
                                                                       :data-tippy-content="`
                                                                            <h6>ID：${subReply.spid}</h6><hr />
                                                                            收录时间：${subReply.created_at}<br />
                                                                            最后更新：${subReply.updated_at}`">
                                                                        <i class="fas fa-info"></i>
                                                                    </a>
                                                                </div>
                                                                <span class="badge badge-pill badge-info" :data-tippy-content="subReply.postTime">{{ moment(subReply.postTime).fromNow() }}</span>
                                                            </div>
                                                        </template>
                                                        <div v-html="subReply.content"></div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                </scroll-list>
                <div v-if="! loadingNewPosts && isLastPage" class="reply-list-next-page p-4">
                    <div class="row align-items-center">
                        <div class="col"><hr /></div>
                        <div class="w-auto" v-for="page in [postsData.pages]">
                            <span v-if="page.currentPage == page.lastPage" class="h4">已经到底了~</span>
                            <button v-else @click="loadNewThreadsPage($event.currentTarget, page.currentPage + 1)"
                                    type="button" class="btn btn-secondary">
                                <span class="h4">下一页</span>
                            </button>
                        </div>
                        <div class="col"><hr /></div>
                    </div>
                </div>
            </div>
        </template>
        <template id="posts-nav-template">
            <div>
                <button @click="showPostsNav = ! showPostsNav"
                        type="button" class="posts-nav-btn btn btn-light border">
                    <i class="fas fa-bars"></i>
                </button>
                <transition name="posts-nav">
                    <div v-show="showPostsNav"
                         class="posts-nav border-left shadow-sm bg-light">
                        <template v-for="postsData in postPages">
                            <template v-for="currentPage in [postsData.pages.currentPage]">
                                <nav :id="`posts-nav-page-${currentPage}`" class="posts-nav-page nav">
                                    <a v-text="`第${currentPage}页`" href="#!" data-toggle="collapse"
                                       :data-target="`.posts-nav-thread[data-parent='#posts-nav-page-${currentPage}']`"
                                       aria-expanded="true" aria-controls="posts-nav"
                                       class="posts-nav-page-link border border-primary btn"
                                       v-class-list="{ 'btn-info': latestObservedReplyLocation.page == currentPage }"></a>
                                    <div v-for="thread in postsData.threads" :key="thread.tid"
                                         :data-parent="`#posts-nav-page-${currentPage}`"
                                         class="posts-nav-thread collapse show">
                                        <a v-text="thread.title" href="#!" data-toggle="collapse"
                                           :id="`posts-nav-thread-${thread.tid}`"
                                           :data-target="`.posts-nav-reply[data-parent='#posts-nav-thread-${thread.tid}']`"
                                           aria-expanded="true" aria-controls="posts-nav-reply"
                                           class="posts-nav-thread-link text-left btn"
                                           v-class-list="{ 'btn-info': latestObservedReplyLocation.tid == thread.tid }"></a>
                                        <nav class="nav">
                                            <a v-for="reply in thread.replies" :key="reply.pid"
                                               @click="$parent.navigateToReplyItem(reply.pid)"
                                               v-scroll-into-view="latestObservedReplyLocation.pid == reply.pid"
                                               v-text="`${reply.floor}L`" :href="`#${reply.pid}`"
                                               :data-parent="`#posts-nav-thread-${thread.tid}`"
                                               class="posts-nav-reply collapse show btn"
                                               v-class-list="{ 'btn-info': latestObservedReplyLocation.pid == reply.pid }"></a>
                                        </nav>
                                        <div class="border-top"></div>
                                    </div>
                                </nav>
                            </template>
                        </template>
                    </div>
                </transition>
            </div>
        </template>
        <template id="post-list-pages-template">
            <div>
                <form @submit.prevent="submitQueryForm()" class="mt-3">
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryPostID">贴子ID</label>
                        <div id="queryPostID" class="input-group col">
                            <div class="input-group-prepend">
                                <span class="input-group-text">tid</span>
                            </div>
                            <select v-model="queryData.query.tidRange"
                                    data-param="tidRange" id="queryTidRange" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                            </select>
                            <input v-model="queryData.params.tid"
                                   data-param="tid" id="queryTid" type="number"
                                   class="form-control" placeholder="5000000000" aria-label="tid" />
                            <div class="input-group-prepend">
                                <span class="input-group-text">pid</span>
                            </div>
                            <select v-model="queryData.query.pidRange"
                                    data-param="pidRange" id="queryPidRange" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                            </select>
                            <input v-model="queryData.params.pid"
                                   data-param="pid" id="queryPid" type="number"
                                   class="form-control" placeholder="15000000000" aria-label="pid" />
                            <div class="input-group-prepend">
                                <span class="input-group-text">spid</span>
                            </div>
                            <select v-model="queryData.query.spidRange"
                                    data-param="spidRange" id="querySpidRange" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                            </select>
                            <input v-model="queryData.params.spid"
                                   data-param="spid" id="querySpid" type="number"
                                   class="form-control" placeholder="15000000000" aria-label="spid" />
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryFid">贴吧</label>
                        <div class="col-3 input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-filter"></i>
                                </span>
                            </div>
                            <select v-model="queryData.query.fid"
                                    data-param="fid" id="queryFid" class="form-control">
                                <option value="undefined">未指定</option>
                                <option v-for="forum in forumsList" :key="forum.fid"
                                        v-text="forum.name" :value="forum.fid"></option>
                            </select>
                        </div>
                        <label class="text-center col-1 col-form-label">贴子类型</label>
                        <div class="input-group my-auto col">
                            <div class="custom-checkbox custom-control custom-control-inline">
                                <input v-model="queryData.query.postType"
                                       data-param="postType" id="queryPostTypeThread" type="checkbox" value="thread" class="custom-control-input">
                                <label class="custom-control-label" for="queryPostTypeThread">主题贴</label>
                            </div>
                            <div class="custom-checkbox custom-control custom-control-inline">
                                <input v-model="queryData.query.postType"
                                       data-param="postType" id="queryPostTypeReply" type="checkbox" value="reply" class="custom-control-input">
                                <label class="custom-control-label" for="queryPostTypeReply">回复贴</label>
                            </div>
                            <div class="custom-checkbox custom-control custom-control-inline">
                                <input v-model="queryData.query.postType"
                                       data-param="postType" id="queryPostTypeSubReply" type="checkbox" value="subReply" class="custom-control-input">
                                <label class="custom-control-label" for="queryPostTypeSubReply">楼中楼</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryOrder">排序方式</label>
                        <div id="queryOrder" class="col-8 input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-sort-amount-down"></i>
                                </span>
                            </div>
                            <select v-model="queryData.query.orderBy"
                                    data-param="orderBy" id="queryOrderBy" class="form-control col">
                                <option value="default">默认（单贴查询按发贴时间正序；单吧/搜索查询倒序）</option>
                                <option value="postTime">发贴时间</option>
                                <optgroup label="贴子ID">
                                    <option value="tid">主题贴tid</option>
                                    <option value="pid">回复贴pid</option>
                                    <option value="spid">楼中楼spid</option>
                                </optgroup>
                            </select>
                            <select v-model="queryData.query.orderDirection"
                                    data-param="orderDirection" id="queryOrderDirection" class="col-4 form-control">
                                <option value="ASC">正序（从小/旧至大/新）</option>
                                <option value="DESC">倒序（从大/新至小/旧）</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryPostTime">发贴时间</label>
                        <div id="queryPostTime" class="col-7 input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                            </div>
                            <input v-model="queryData.query.postTimeStart"
                                   data-param="postTimeStart" id="queryPostTimeStart" type="datetime-local" class="custom-query-param form-control">
                            <div class="input-group-prepend">
                                <span class="input-group-text">至</span>
                            </div>
                            <input v-model="queryData.query.postTimeEnd"
                                   data-param="postTimeEnd" id="queryPostTimeEnd" type="datetime-local" class="custom-query-param form-control">
                        </div>
                    </div>
                    <div id="queryCustomQueryParamsCollapse" class="collapse">
                        <div class="card-body">
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryLatestReplyTime">最后回复时间</label>
                                <div id="queryLatestReplyTime" class="col-7 input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">
                                            <i class="far fa-calendar-alt"></i>
                                        </span>
                                    </div>
                                    <input v-model="queryData.query.latestReplyTimeStart"
                                           data-param="latestReplyTimeStart" id="queryLatestReplyTimeStart" type="datetime-local" class="custom-query-param form-control">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">至</span>
                                    </div>
                                    <input v-model="queryData.query.latestReplyTimeEnd"
                                           data-param="latestReplyTimeEnd" id="queryLatestReplyTimeEnd" type="datetime-local" class="custom-query-param form-control">
                                </div>
                                <small class="col align-self-center">仅主题贴</small>
                            </div>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadTitle">主题贴标题</label>
                                <div class="col-8 input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.threadTitleRegex"
                                                       data-param="threadTitleRegex" id="queryThreadTitleRegex" type="checkbox" value="" class="custom-query-param custom-control-input">
                                                <label class="custom-control-label" for="queryThreadTitleRegex">正则</label>
                                            </div>
                                        </div>
                                    </div>
                                    <input v-model="queryData.query.threadTitle"
                                           data-param="threadTitle" id="queryThreadTitle" type="text" placeholder="模糊匹配 仅主题贴" class="custom-query-param form-control">
                                </div>
                                <small class="col align-self-center">仅主题贴</small>
                            </div>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryPostContent">内容关键词</label>
                                <div class="col-8 input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.postContentRegex"
                                                       data-param="postContentRegex" id="queryPostContentRegex" type="checkbox" value="" class="custom-query-param custom-control-input">
                                                <label class="custom-control-label" for="queryPostContentRegex">正则</label>
                                            </div>
                                        </div>
                                    </div>
                                    <input v-model="queryData.query.postContent"
                                           data-param="postContent" id="queryPostContent" type="text" placeholder="模糊匹配 非正则下空格分割关键词" class="custom-query-param form-control">
                                </div>
                                <small class="col align-self-center">仅非主题贴</small>
                            </div>
                            <fieldset class="border rounded col-10 p-3 form-group form-row">
                                <legend class="h6 w-auto">
                                    用户信息 <small>主题贴下为楼主</small>
                                </legend>
                                <div class="form-inline form-group form-row">
                                    <select-user @changed="selectUserChanged"
                                                 :select-by-options-name="{
                                                     uid: 'userID',
                                                     name: 'userName',
                                                     displayName: 'userDisplayName'
                                                 }"></select-user>
                                    <label class="col-2 col-form-label">查询范围</label>
                                    <div class="input-group my-auto col">
                                        <div class="custom-checkbox custom-control custom-control-inline">
                                            <input v-model="queryData.query.userType"
                                                   data-param="userType" id="queryUserTypeAuthor" type="checkbox" value="author" class="custom-query-param custom-control-input">
                                            <label class="custom-control-label" for="queryUserTypeAuthor">发贴人</label>
                                        </div>
                                        <div class="custom-checkbox custom-control custom-control-inline">
                                            <input v-model="queryData.query.userType"
                                                   data-param="userType" id="queryUserTypeLatestReplier" type="checkbox" value="latestReplier" class="custom-query-param custom-control-input">
                                            <label class="custom-control-label" for="queryUserTypeLatestReplier">最后回复人（仅主题贴）</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-inline form-group form-row">
                                    <label class="col-2 col-form-label" for="queryUserManagerType">吧务级别</label>
                                    <select v-model="queryData.query.userManagerType"
                                            data-param="userManagerType" id="queryUserManagerType" class="custom-query-param form-control col-2">
                                        <option value="default">所有</option>
                                        <option value="NULL">吧友</option>
                                        <option value="manager">吧主</option>
                                        <option value="assist">小吧主</option>
                                        <option value="voiceadmin">语音小编</option>
                                    </select>
                                    <label class="col-1 col-form-label" for="queryUserExpGrade">等级</label>
                                    <div class="col-2 input-group">
                                        <select v-model="queryData.query.userExpGradeRange"
                                                data-param="userExpGradeRange" id="queryUserExpGradeRange" class="custom-query-param form-control">
                                            <option>&lt;</option>
                                            <option>=</option>
                                            <option>&gt;</option>
                                        </select>
                                        <input v-model="queryData.query.userExpGrade"
                                               data-param="userExpGrade" id="queryUserExpGrade" type="number" placeholder="18" class="custom-query-param form-control">
                                    </div>
                                    <label class="col-1 col-form-label" for="queryUserGender">性别</label>
                                    <select v-model="queryData.query.userGender"
                                            data-param="userGender" id="queryUserGender" class="custom-query-param form-control col-1">
                                        <option value="default">不限</option>
                                        <option value="0">未指定（显示为男）</option>
                                        <option value="1">男 ♂</option>
                                        <option value="2">女 ♀</option>
                                    </select>
                                </div>
                            </fieldset>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadProperty">主题贴属性</label>
                                <div id="queryThreadProperty" class="col input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.threadProperty"
                                                       data-param="threadProperty" id="queryThreadPropertyGood" type="checkbox" value="good" class="custom-query-param custom-control-input">
                                                <label class="text-danger font-weight-bold custom-control-label" for="queryThreadPropertyGood">精品</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.threadProperty"
                                                       data-param="threadProperty" id="queryThreadPropertySticky" type="checkbox" value="sticky" class="custom-query-param custom-control-input">
                                                <label class="text-primary font-weight-bold custom-control-label" for="queryThreadPropertySticky">置顶</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-inline form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadReplyNumGroup">主题贴回复数</label>
                                <div id="queryThreadReplyNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.threadReplyNumRange"
                                            data-param="threadReplyNumRange" id="queryThreadReplyNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.threadReplyNum"
                                           data-param="threadReplyNum" id="queryThreadReplyNum" type="number" placeholder="100" class="custom-query-param form-control">
                                </div>
                                <label class="col-2 col-form-label" for="queryReplySubReplyNumGroup">楼中楼回复数</label>
                                <div id="queryReplySubReplyNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.replySubReplyNumRange"
                                            data-param="replySubReplyNumRange" id="queryReplySubReplyNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.replySubReplyNum"
                                           data-param="replySubReplyNum" id="queryReplySubReplyNum" type="number" placeholder="仅回复贴" class="custom-query-param form-control">
                                </div>
                            </div>
                            <div class="form-inline form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadViewNumGroup">主题贴查看量</label>
                                <div id="queryThreadViewNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.threadViewNumRange"
                                            data-param="threadViewNumRange" id="queryThreadViewNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.threadViewNum"
                                           data-param="threadViewNum" id="queryThreadViewNum" type="number" placeholder="100" class="custom-query-param form-control">
                                </div>
                                <label class="col-2 col-form-label" for="queryThreadShareNumGroup">主题贴分享量</label>
                                <div id="queryThreadShareNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.threadShareNumRange"
                                            data-param="threadShareNumRange" id="queryThreadShareNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.threadShareNum"
                                           data-param="threadShareNum" id="queryThreadShareNum" type="number" placeholder="100" class="custom-query-param form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <button type="submit" class="btn btn-primary">查询</button>
                        <button class="ml-2 btn btn-light" type="button"
                                data-toggle="collapse" data-target="#queryCustomQueryParamsCollapse"
                                aria-expanded="false" aria-controls="queryCustomQueryParamsCollapse">搜索查询参数</button>
                    </div>
                </form>
                <posts-nav :post-pages="postPages"
                           :latest-observed-reply-pid="latestObservedReplyPid"></posts-nav>
                <post-list v-for="(postsData, currentListPage) in postPages"
                           :key="genPostListKey(currentListPage)"
                           :posts-data="postsData"
                           :loading-new-posts="loadingNewPosts"
                           :is-last-page="currentListPage == postPages.length - 1"
                           ref="postLists"></post-list>
                <loading-list-placeholder v-if="loadingNewPosts"></loading-list-placeholder>
            </div>
        </template>
        <template id="error-404-placeholder-template">
            <div class="text-center" style="font-size: 8em">
                <hr />404
            </div>
        </template>
        <div id="post-list-pages">
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
    @verbatim
        <script>
            'use strict';
            $$initialNavBar('post');

            const loadingListPlaceholderComponent = Vue.component('loading-list-placeholder', {
                template: '#loading-list-placeholder-template'
            });

            const error404PlaceholderComponent = Vue.component('error-404-placeholder', {
                template: '#error-404-placeholder-template'
            });

            const postListComponent = Vue.component('post-list', {
                template: '#post-list-template',
                props: { // receive from post list pages component
                    postsData: Object,
                    loadingNewPosts: Boolean,
                    isLastPage: Boolean
                },
                data: function () {
                    return {
                        // import global variables
                        $$baseUrl,
                        $$getTiebaPostLink,
                        $$getTiebaUserLink,
                        $$getTiebaUserAvatarUrl,
                        moment,
                        hoveringSubReplyItem: 0 // for display item's right floating hide buttons
                    };
                },
                computed: {
                    previousPageUrl: function () { // cache attr to ensure each list component's url won't be updated after page param change
                        // generate an new absolute url with previous page params which based on current route path
                        let urlWithNewPage = this.$route.fullPath.replace(`/page/${this.$route.params.page}`, `/page/${this.$route.params.page - 1}`);
                        return `${$$baseUrlDir}${urlWithNewPage}`;
                    }
                },
                methods: {
                    getUserData: function (uid) {
                        return _.find(this.postsData.users, { uid: uid }) || [ // thread latest replier uid might be unknown
                            { id: 0 },
                            { uid: 0 },
                            { name: '未知用户' },
                            { displayName: '未知用户' },
                            { avatarUrl: null },
                            { gender: 0 },
                            { fansNickname: null },
                            { iconInfo: [] }
                        ];
                    },
                    loadNewThreadsPage: function (eventDOM, newPage) {
                        let pagingRouteName = this.$route.name.endsWith('+p') ? this.$route.name : this.$route.name + '+p';
                        this.$router.push({ name: pagingRouteName, params: { page: newPage.toString() }, query: this.$route.query }); // route params value should always be string
                    }
                }
            });

            const postsNavComponent = Vue.component('posts-nav', {
                template: '#posts-nav-template',
                directives: {
                    'class-list': { // operate on real dom to prevent vue v-bind:class overriding other js changed class list (such as bootstrap collapse)
                        update: function (el, binding) {
                            _.each(binding.value, (isAdd, className) => {
                                if (isAdd === true) {
                                    $(el).addClass(className);
                                } else {
                                    $(el).removeClass(className);
                                }
                            });
                        }
                    },
                    'scroll-into-view': {
                        update: function (el, binding) {
                            if (binding.value === true) {
                                el.scrollIntoView();
                                if (! $(el).is(':last-child')) {
                                    $('.posts-nav')[0].scrollTop -= 100;
                                }
                            }
                        }
                    }
                },
                props: {
                    postPages: Array,
                    latestObservedReplyPid: Number
                },
                data: function () {
                    return {
                        showPostsNav: false,
                        latestObservedReplyLocation: { page: 0, tid: 0, pid: 0 }
                    };
                },
                computed: {

                },
                watch: {
                    latestObservedReplyPid: function (latestObservedReplyPid) {
                        this.$data.latestObservedReplyLocation = _.map(this.$props.postPages, (postPage) => {
                            let replyParentThread = _.find(postPage.threads, (thread) => {
                                return _.find(thread.replies, { pid: latestObservedReplyPid });
                            });
                            if (replyParentThread != null) { // reply might doesn't existed in current post page
                                return {
                                    page: postPage.pages.currentPage,
                                    tid: replyParentThread.tid,
                                    pid: latestObservedReplyPid
                                };
                            }
                        })[0];
                    }
                },
                created: function () {

                },
                methods: {

                }
            });

            const postListPagesComponent = Vue.component('post-list-pages', {
                template: '#post-list-pages-template',
                data: function () {
                    return {
                        postPages: [], // multi pages of posts list collection
                        loadingNewPosts: false,
                        forumsList: [],
                        queryData: { query: {}, params: {} },
                        customQueryParamsDefaultValue: [
                            { param: 'postType', default: ['thread', 'reply', 'subReply']},
                            { param: 'tidRange', default: '=' },
                            { param: 'pidRange', default: '=' },
                            { param: 'spidRange', default: '=' },
                            { param: 'orderBy', default: 'default' },
                            { param: 'userType', default: ['author'] },
                            { param: 'userExpGradeRange', default: '=' },
                            { param: 'userGender', default: 'default' },
                            { param: 'userManagerType', default: 'default' },
                            { param: 'threadProperty', default: [] },
                            { param: 'threadReplyNumRange', default: '=' },
                            { param: 'replySubReplyNumRange', default: '=' },
                            { param: 'threadViewNumRange', default: '=' },
                            { param: 'threadShareNumRange', default: '=' }
                        ],
                        arrayableCustomQueryParams: [
                            'postType',
                            'userType',
                            'threadProperty'
                        ],
                        postListKeyCache: [],
                        latestObservedReplyPid: 0
                    };
                },
                watch: {
                    loadingNewPosts: function (loadingNewPosts) {
                        if (loadingNewPosts) {
                            this.$parent.showError404Placeholder = false;
                            this.$parent.showFirstLoadingPlaceholder = false;
                        }
                    },
                    postPages: function () {
                        this.$nextTick(() => { // run jquery on posts lists after vue components stop updating
                            let vue = this;

                            { // query params check
                                _.each(this.$data.customQueryParamsDefaultValue, (param) => { // set default params value on param form when it's not set
                                    if (Reflect.get(vue.$data.queryData.query, param.param) == null) {
                                        Reflect.set(vue.$data.queryData.query, param.param, param.default);
                                    }
                                });

                                let isRouteCustomQuery = vue.$route.query == null;
                                if (vue.$data.queryData.query.orderDirection == null) { // set default order direction which base on posts query type
                                    vue.$data.queryData.query.orderDirection = isRouteCustomQuery && _.isEmpty(_.omit(vue.$data.queryData.params, 'fid')) ? 'DESC' : 'ASC';
                                }

                                let radioLikeCheckboxParamsGroup = [
                                    [
                                        'queryPostTypeThread',
                                        'queryPostTypeReply',
                                        'queryPostTypeSubReply'
                                    ],
                                    [
                                        'queryUserTypeAuthor',
                                        'queryUserTypeLatestReplier'
                                    ]
                                ];
                                const checkRadioLikeCheckboxParamsGroupValue = (event) => { // ensure there's at least one post type checked
                                    _.each(radioLikeCheckboxParamsGroup, (paramsGroup) => {
                                        if (paramsGroup.includes(event.target.id)
                                            && _.map(paramsGroup, (domID) => {
                                                return $(`#${domID}`).prop('checked')
                                            }).every((postTypesCheck) => { // is all post type unchecked
                                                return ! postTypesCheck;
                                            })) {
                                            event.preventDefault();
                                        }
                                    });
                                };
                                _.each(_.flatten(radioLikeCheckboxParamsGroup), (domID) => {
                                    $(`#${domID}`).off('click').on('click', checkRadioLikeCheckboxParamsGroupValue);
                                });

                                const checkUserInfoParamExcludingLatestReplier = () => {
                                    let userInfoParamsExcludingLatestReplier = [
                                        'userExpGrade',
                                        'userExpGradeRange',
                                        'userManagerType'
                                    ];
                                    let latestReplierChecked = vue.$data.queryData.query.userType.includes('latestReplier');
                                    _.each(userInfoParamsExcludingLatestReplier, (userInfoParam) => {
                                        $(`[data-param=${userInfoParam}]`).prop('disabled', latestReplierChecked);
                                        if (latestReplierChecked) {
                                            Reflect.deleteProperty(vue.$data.queryData.query, userInfoParam);
                                        }
                                    });
                                };
                                checkUserInfoParamExcludingLatestReplier();
                                $('#queryUserTypeLatestReplier').off('change').on('change', checkUserInfoParamExcludingLatestReplier);

                                const checkQueryParamsRequiredPostType = () => {
                                    //let postType = _.sortBy(vue.$data.queryData.query.postType); // sort post type array to compare without effect from order
                                    let queryPostTypes = vue.$data.queryData.query.postType;

                                    let paramsRequiredPostType = [
                                        { domID: 'queryUserTypeLatestReplier', postType: ['thread'] },
                                        { domID: 'queryThreadTitle', postType: ['thread'] },
                                        { domID: 'queryLatestReplyTimeStart', postType: ['thread'] },
                                        { domID: 'queryLatestReplyTimeEnd', postType: ['thread'] },
                                        { domID: 'queryThreadReplyNum', postType: ['thread'] },
                                        { domID: 'queryReplySubReplyNum', postType: ['reply'] },
                                        { domID: 'queryThreadViewNum', postType: ['thread'] },
                                        { domID: 'queryThreadShareNum', postType: ['thread'] },
                                        { domID: 'queryThreadPropertyGood', postType: ['thread'] },
                                        { domID: 'queryThreadPropertySticky', postType: ['thread'] },
                                        { domID: 'queryPostContent', postType: ['reply', 'subReply'] },
                                    ];
                                    _.each(paramsRequiredPostType, (param) => {
                                        let enabledParams = [];
                                        let queryParamDOM = $(`#${param.domID}`);
                                        if (_.isEqual(_.difference(queryPostTypes, param.postType), [])) {
                                            queryParamDOM.prop('disabled', false);
                                            enabledParams.push(param.domID);
                                        } else if (! enabledParams.includes(param.domID)) { // ensure disabling param hadn't enabled before
                                            queryParamDOM.prop('disabled', true);
                                            let queryParamName = queryParamDOM.data('param');
                                            let queryParamNullValue = vue.$data.arrayableCustomQueryParams.includes(queryParamName) ? [] : ''; // arrayable query param's default value should be []
                                            Reflect.set(vue.$data.queryData.query, queryParamName, queryParamNullValue);
                                        }
                                    });

                                    let orderByParamRequiredPostType = [
                                        { orderName: 'tid', postType: ['thread', 'reply', 'subReply'] },
                                        { orderName: 'pid', postType: ['reply', 'subReply'] },
                                        { orderName: 'spid', postType: ['subReply'] },
                                    ];
                                    _.each(orderByParamRequiredPostType, (param) => {
                                        let enabledOrderBy = [];
                                        let orderByOptionDOM = $(`#queryOrderBy [value=${param.orderName}]`);
                                        _.each(queryPostTypes, (queryPostType) => {
                                            if (param.postType.includes(queryPostType)) {
                                                orderByOptionDOM.prop('disabled', false);
                                                enabledOrderBy.push(param.orderName);
                                            } else if (! enabledOrderBy.includes(param.orderName)) { // ensure disabling orderBy hadn't enabled before
                                                orderByOptionDOM.prop('disabled', true);
                                            }
                                        });
                                    });
                                    if ($(`#queryOrderBy [value=${vue.$data.queryData.query.orderBy}]`).prop('disabled')) { // only change to default order when current selecting orderBy had disabled
                                        vue.$data.queryData.query.orderBy = 'default';
                                        vue.$forceUpdate();
                                    }
                                };
                                checkQueryParamsRequiredPostType();
                                $('[data-param=postType]').off('change').on('change', checkQueryParamsRequiredPostType);

                                const checkCustomQueryAvailable = () => {
                                    let isCustomQueryAvailable = $('#queryFid').prop('value') !== 'undefined'
                                        || ! _.isEmpty(vue.$data.queryData.params.tid)
                                        || ! _.isEmpty(vue.$data.queryData.params.pid)
                                        || ! _.isEmpty(vue.$data.queryData.params.spid);
                                    let customQueryParamsDOM = $('.custom-query-param');
                                    customQueryParamsDOM.prop('disabled', ! isCustomQueryAvailable);
                                    _.each(customQueryParamsDOM, (dom) => {
                                        let customQueryParamName = $(dom).data('param');
                                        if (isCustomQueryAvailable) {
                                            let customQueryParamDefaultValue = _.find(vue.$data.customQueryParamsDefaultValue, { param: customQueryParamName });
                                            if (customQueryParamDefaultValue != null) {
                                                Reflect.set(vue.$data.queryData.query, customQueryParamName, customQueryParamDefaultValue.default);
                                            }
                                        } else {
                                            /*if (customQueryParamDefaultValue != null) { // reset param value to default if it has
                                                Reflect.set(vue.$data.queryData.query, customQueryParamName, customQueryParamDefaultValue.default);
                                            } else */{
                                                Reflect.deleteProperty(vue.$data.queryData.query, customQueryParamName);
                                            }
                                        }
                                    });
                                    checkQueryParamsRequiredPostType();
                                };
                                checkCustomQueryAvailable();
                                $('#queryFid').off('change').on('change', checkCustomQueryAvailable);

                                vue.$forceUpdate();
                            }

                            {
                                this.replyItemEventRegister();

                                // scroll viewport to reply item anchor by url hash pid after posts list loaded
                                let urlHashReplyPid = this.$route.hash === '#!' ? null : this.$route.hash.substring(1); // ignore #! shebang url hash
                                if (urlHashReplyPid != null) {
                                    this.navigateToReplyItem(urlHashReplyPid);
                                }
                            }
                        });
                    }
                },
                created: function () {
                    let customQueryParams = _.cloneDeep(this.$route.query);
                    let queryParams = _.omit(_.cloneDeep(this.$route.params), 'pathMatch'); // prevent store pathMatch property into params due to https://github.com/vuejs/vue-router/issues/2503
                    _.each(this.$data.arrayableCustomQueryParams, (arrayableParamName) => {
                        let arrayableParamValue = Reflect.get(customQueryParams, arrayableParamName);
                        if (arrayableParamValue != null && ! _.isArray(arrayableParamValue)) { // https://github.com/vuejs/vue-router/issues/1232
                            Reflect.set(customQueryParams, arrayableParamName, [arrayableParamValue]);
                            // vue.$route should be immutable but here we have to wrap arrayable query params value
                            Reflect.set(this.$route.query, arrayableParamName, [Reflect.get(this.$route.query, arrayableParamName)]);
                        }
                    });

                    this.$data.queryData = { query: customQueryParams, params: queryParams };
                    $$loadForumsList().then((forumsList) => {
                        this.$data.forumsList = forumsList;
                        this.loadPageData(this.$data.queryData.params, this.$data.queryData.query, true); // wait for forums list finish loading
                    });
                },
                methods: {
                    selectUserChanged: function (event) {
                        let queryParams = this.$data.queryData.query;
                        // reset all user select params to prevent old value remains after <select-user> params changed
                        queryParams.userID = null;
                        queryParams.userName = null;
                        queryParams.userDisplayName = null;
                        queryParams = _.merge(queryParams, event);
                    },
                    submitQueryForm: function () {
                        let queryParams = _.chain(this.$data.queryData.params)
                            .omit('page')
                            .omitBy(_.isEmpty) // omitBy will remove empty param values like empty string
                            .toPairs()
                            .map((param) => {
                                return _.fromPairs([param]); // convert { k: v, k: v } to [[k, v], [k ,v]]
                            })
                            .sortBy((param) => {
                                return _.indexOf(['tid', 'pid', 'spid'], _.keys(param)[0]); // sort params array with given order
                            })
                            .value();
                        let queryParamsPath = '';
                        _.each(queryParams, (param) => {
                            if (_.values(param)[0] !== "") {
                                queryParamsPath += `/${_.keys(param)[0]}/${_.values(param)[0]}`;
                            }
                        });

                        let customQueryParams = _.omitBy(_.cloneDeep(this.$data.queryData.query), _.isEmpty); // omitBy will remove empty query param values like empty string
                        if (_.isEqual(_.sortBy(customQueryParams.postType), _.sortBy(['thread', 'reply', 'subReply']))) { // sort post type array to compare without effect from order
                            delete customQueryParams.postType;
                        }

                        if (customQueryParams.fid === 'undefined') {
                            delete customQueryParams.fid;
                        }
                        if (customQueryParams.orderBy === 'default') {
                            delete customQueryParams.orderDirection;
                        }

                        _.each(this.$data.customQueryParamsDefaultValue, (param) => { // remove default value params
                            let paramName = param.param;
                            if (Reflect.get(customQueryParams, paramName) === param.default) {
                                Reflect.deleteProperty(customQueryParams, paramName);
                            }
                        });

                        let queryPostsIDRangeParams = [
                            'tidRange',
                            'pidRange',
                            'spidRange'
                        ];
                        _.each(queryPostsIDRangeParams, (param) => { // remove range param when posts id param is unset
                            if (Reflect.get(this.$data.queryData.params, param.replace('Range', '')) === undefined) {
                                Reflect.deleteProperty(customQueryParams, param);
                            }
                        });

                        let userInfoParams = [
                            'userManagerType',
                            'userID',
                            'userName',
                            'userDisplayName',
                            'userExpGradeRange',
                            'userExpGrade',
                            'userGender'
                        ];
                        if (_.every(userInfoParams, (param) => {
                            return Reflect.get(customQueryParams, param) == null;
                        })) {
                            delete customQueryParams.userType;
                        }

                        this.$router.push({ path: `/post${queryParamsPath}`, query: customQueryParams });
                    },
                    loadPageData: function (routeParams, routeQueryStrings, shouldReplacePage) {
                        const groupSubRepliesByAuthor = (data) => {
                            data.threads.forEach((thread) => {
                                thread.replies.forEach((reply) => {
                                    reply.subReplies.forEach((subReply, index, subReplies) => {
                                        // group sub replies item by continuous and same author info
                                        let previousSubReply = subReplies[index - 1];
                                        if (previousSubReply !== undefined
                                            && subReply.authorUid === previousSubReply.authorUid
                                            && subReply.authorManagerType === previousSubReply.authorManagerType
                                            && subReply.authorExpGrade === previousSubReply.authorExpGrade) {
                                            _.last(subReplies).push(subReply);
                                        } else {
                                            subReplies.push([subReply]);
                                        }
                                    });
                                    // remove ungrouped sub replies
                                    reply.subReplies = reply.subReplies.filter((subReply) => {
                                        return _.isArray(subReply);
                                    });
                                });
                            });
                            return data;
                        };

                        let ajaxStartTime = Date.now();
                        let ajaxQueryString = _.merge({}, routeParams, routeQueryStrings); // deep clone
                        if (_.isEmpty(ajaxQueryString)) {
                            new Noty({ timeout: 3000, type: 'info', text: '请选择贴吧或/并输入查询参数'}).show();
                            this.$data.postPages = []; // clear posts pages data will emit posts pages updated event after initial load
                            this.$parent.showFirstLoadingPlaceholder = false;
                            return;
                        }
                        if (shouldReplacePage) {
                            this.$data.postPages = []; // clear posts pages data before request to show loading placeholder
                        }

                        if (window.$previousPostsQueryAjax != null) { // cancel previous loading query ajax to prevent conflict
                            window.$previousPostsQueryAjax.abort();
                        }
                        this.$data.loadingNewPosts = true;
                        $$reCAPTCHACheck().then((token) => {
                            ajaxQueryString = $.param(_.merge(ajaxQueryString, token));
                            window.$previousPostsQueryAjax = $.getJSON(`${$$baseUrl}/api/postsQuery`, ajaxQueryString);
                            window.$previousPostsQueryAjax
                                .done((ajaxData) => {
                                    ajaxData = groupSubRepliesByAuthor(ajaxData);
                                    let pagesInfo = ajaxData.pages;

                                    if (shouldReplacePage) { // is requesting new pages data on same query params or loading new data on different query params
                                        //$('.post-list *').off(); // remove all previous posts list children dom event to prevent re-hiding wrong reply item after load
                                        this.$data.postPages = [ajaxData];
                                    } else {
                                        this.$data.postPages.push(ajaxData);
                                    }

                                    new Noty({ timeout: 3000, type: 'success', text: `已加载第${pagesInfo.currentPage}页 ${pagesInfo.currentItems}条贴子 耗时${Date.now() - ajaxStartTime}ms`}).show();
                                    this.changeDocumentTitle(this.$route);
                                })
                                .fail((jqXHR) => {
                                    this.$data.postPages = [];
                                    this.$parent.showError404Placeholder = true;
                                })
                                .always(() => {
                                    this.$data.loadingNewPosts = false
                                });
                        });
                    },
                    changeDocumentTitle: function (route, newPage = null, threadTitle = null) {
                        newPage = newPage || route.params.page || 1;
                        if (! _.isEmpty(this.$data.postPages)) { // make sure it's not 404
                            let forumName = `${this.$data.postPages[0].forum.name}吧`;
                            if (route.params.tid != null) {
                                if (threadTitle == null) {
                                    _.each(this.$data.postPages, (item) => {
                                        threadTitle = (_.find(item.threads, { tid: parseInt(route.params.tid) }) || {}).title;
                                    });
                                }
                                document.title = `第${newPage}页 - 【${forumName}】${threadTitle} - 贴子查询 - 贴吧云监控`;
                            } else {
                                document.title = `第${newPage}页 - ${forumName} - 贴子查询 - 贴吧云监控`;
                            }
                        }
                    },
                    replyItemEventRegister: function () {
                        $$tippyInital();
                        $$tiebaImageZoomEventRegister();
                    },
                    replyItemObserveEvent: function (isVisible, observer) {
                        this.replyItemEventRegister();
                        let replyItem = $(observer.target);
                        let replyPid = parseInt(replyItem.prop('id'));
                        if (isVisible) {
                            this.$data.latestObservedReplyPid = replyPid;
                            let currentPage = replyItem.parents('.post-list').data('page').toString();
                            let threadTitle = replyItem.parents('.thread-item').data('title');
                            this.$router.replace({
                                params: { page: currentPage },
                                hash: `#${replyPid}`,
                                query: this.$route.query
                            });
                            this.changeDocumentTitle(this.$route, currentPage, threadTitle);
                        }
                    },
                    genPostListKey: function (currentListPage) {
                        let keyCache = this.$data.postListKeyCache[currentListPage];
                        if (keyCache == null) {
                            keyCache = this.$data.postListKeyCache[currentListPage] = `i-${currentListPage + 1}@${JSON.stringify(_.merge({}, this.$route.params, this.$route.query))}`; // deep clone
                        }
                        return keyCache;
                    },
                    navigateToReplyItem: function (pid) {
                        pid = parseInt(pid);
                        _.each(this.$refs.postLists, (postList) => {
                            let scrollList = postList.$refs.threadItemsScrollList;
                            _.each(scrollList.$props.items, (thread, threadKeyInScrollList) => {
                                if (_.find(thread.replies, { pid } ) != null) {
                                    scrollList.$data.displayingItemsID = scrollList.getDisplayIndexRange(0, scrollList.$props.items.length, threadKeyInScrollList, scrollList.$props.itemsShowingNum);
                                    return;
                                }
                            });
                        });
                    }
                },
                beforeRouteUpdate (to, from, next) {
                    // when clicking floating navigate bar #hash link, post type query param might be string instead of array
                    _.each(this.$data.arrayableCustomQueryParams, (arrayableParamName) => {
                        let arrayableParamValue = Reflect.get(to.query, arrayableParamName);
                        if (arrayableParamValue != null && ! _.isArray(arrayableParamValue)) {
                            Reflect.set(to.query, arrayableParamName, [arrayableParamValue]);
                        }
                    });

                    let isRouteParamsChanged = ! _.isEqual(to.params, from.params);
                    let isRouteParamsExceptPageChanged = ! _.isEqual(_.omit(to.params, ['page']), _.omit(from.params, ['page']));
                    let isQueryStringChanged = ! _.isEqual(to.query, from.query);
                    if (isRouteParamsChanged || isQueryStringChanged) { // only request new data when route query params or query string changed
                        let isPageAlreadyLoaded = to.query != null // if there's query string should always reload data
                            && ! isRouteParamsExceptPageChanged
                            && ! isQueryStringChanged
                            && _.filter(this.$data.postPages, (item) => {
                                return item.pages.currentPage === (parseInt(to.params.page) || 1);
                            }).length !== 0;
                        let shouldReplacePage = isQueryStringChanged || isRouteParamsExceptPageChanged;

                        if (shouldReplacePage) {
                            this.$data.postListKeyCache = [];
                        }
                        if (! isPageAlreadyLoaded) {
                            this.loadPageData(to.params, to.query, shouldReplacePage);
                        }
                    }
                    next(); // pass any route changes
                }
            });

            const postListVue = new Vue({
                el: '#post-list-pages',
                data: function () {
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
                            name: 'postsQuery',
                            path: '/post',
                            component: postListPagesComponent,
                            children: [
                                { name: 'nullQuery+p', path: 'page/:page' },
                                { name: 'tid', path: 't/:tid', children: [{ name:'tid+p', path: 'page/:page' }] },
                                { name: 't+pid', path: 't/:tid/p/:pid', children: [{ name:'t+pid+p', path: 'page/:page' }] },
                                { name: 't+spid', path: 't/:tid/sp/:spid', children: [{ name:'t+spid+p', path: 'page/:page' }] },
                                { name: 't+p+spid', path: 't/:tid/p/:pid/sp/:spid', children: [{ name:'t+p+spid+p', path: 'page/:page' }] },
                                { name: 'pid', path: 'p/:pid', children: [{ name:'pid+p', path: 'page/:page' }] },
                                { name: 'p+spid', path: 'p/:pid/sp/:spid', children: [{ name:'p+spid+p', path: 'page/:page' }] },
                                { name: 'spid', path: 'sp/:spid', children: [{ name:'spid+p', path: 'page/:page' }] },
                                { name: 'customQuery', path: '*', query: '*', children: [{ name:'customQuery+p', path: 'page/:page' }]},
                            ]
                        }
                    ]
                })
            });
        </script>
    @endverbatim
@endsection
