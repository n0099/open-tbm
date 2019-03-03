@extends('layout')
@php($baseUrl = env('APP_URL'))

@section('title', '贴子查询')

@section('container')
    <style>
        .lazyload, .lazyloading {
            opacity: 0;
            background: #f7f7f7 url({{ $baseUrl }}/img/icon-huaji-loading-spinner.gif) no-repeat center;
        }
        .lazyloaded {
            opacity: 1;
            transition: opacity 200ms;
        }
        .loading-icon {
            width: 100px;
            height: 100px;
            background-image: url({{ $baseUrl }}/img/icon-huaji-loading-spinner.gif);
            background-size: 100%;
        }

        .posts-list-placeholder {
            height: 480px;
            background-image: url({{ $baseUrl }}/img/tombstone-posts-list.svg);
            background-size: 100%;
        }

        .floating-posts-nav-btn {
            opacity: 0.5;
            position: fixed;
            bottom: 0;
            right: 0;
            z-index: 1022;
        }
        .floating-posts-nav {
            display: none; /* hide posts nav by default */
            position: fixed;
            bottom: 0;
            right: 0;
            overflow: auto;
            width: 12.5rem;
            height: 100%;
            z-index: 1021;
        }
        .floating-posts-nav a {
            white-space: normal;
            padding: .25rem .5rem .25rem .5rem;
            margin: .25rem;
        }
        .floating-posts-nav a:hover {
            border-color: #17a2b8;
        }
        /*.floating-posts-nav*/ .posts-nav-page {
            width: 100%;
        }
        /*.floating-posts-nav .posts-nav-page*/ .posts-nav-thread {
            margin-left: 10%;
            width: 90%;
        }
        /*.floating-posts-nav .posts-nav-page .posts-nav-thread*/ .posts-nav-reply {
            margin-left: 10%;
            width: 90%;
        }

        .card-body {
            padding: 0.5rem;
        }

        .thread-item {
            margin-top: 1.5rem;
        }

        /*.thread-item*/ .thread-title {
            background-color: #F2F2F2;
        }

        /*.thread-item .reply-item {}*/

        /*.reply-item*/ .reply-title {
            top: 72px;
            margin-top: .625rem;
            border-top: 1px solid #ededed;
            border-bottom: 0;
            background: linear-gradient(rgba(237,237,237,1), rgba(237,237,237,.1));
            z-index: 1019;
        }
        /*.reply-item*/ .reply-info {
            margin: 0 !important;
            border-top: 0 !important;
        }
        /*.reply-info*/ .reply-banner {
            padding-left: 0;
            padding-right: .5rem;
        }
        /*.reply-item .reply-info*/ .reply-body {
            width: 0; /* let reply-body show abreast with reply-banner */
            padding-left: .5rem;
            padding-right: .5rem;
        }
        /*.reply-item .reply-info .reply-banner*/ .reply-user-info {
            z-index: 1018;
            top: 8rem;
            padding: .25rem;
            font-size: 1rem;
            line-height: 140%;
        }

        /*.reply-item .reply-info .reply-body*/ .sub-reply-group {
            margin: 0 0 .25rem .5rem !important;
            padding: .25rem !important;
        }
        /*.sub-reply-group*/ .sub-reply-item {
            padding: .125rem .125rem .125rem .625rem;
        }
        /*.sub-reply-group*/ .sub-reply-item > * {
            padding: .25rem;
        }
        /*.sub-reply-group .sub-reply-item*/ .sub-reply-user-info {
            font-size: 0.9rem;
        }

        .bs-callout {
            padding: .625rem;
            margin: .625rem 0;
        }
    </style>
    @verbatim
        <template id="posts-list-template">
            <div :data-page="postsData.pages.currentPage" class="posts-list">
                <div class="reply-list-previous-page p-2 row align-items-center">
                    <div class="col align-middle"><hr /></div>
                    <div class="w-auto" v-for="page in [postsData.pages]">
                        <div class="p-2 badge badge-light">
                            <a v-if="page.currentPage > 1" class="badge badge-primary" :href="getPerviousPageUrl">上一页</a>
                            <p class="h4" v-text="`第 ${page.currentPage} 页`"></p>
                            <p class="small" v-text="`第 ${page.firstItem}~${page.firstItem + page.currentItems - 1} 条 共 ${page.totalItems} 条`"></p>
                            <span class="h5" v-text="`${postsData.forum.name}吧`"></span>
                        </div>
                    </div>
                    <div class="col align-middle"><hr /></div>
                </div>
                <div v-for="thread in postsData.threads" :data-title="thread.title" class="thread-item card">
                    <div class="thread-title shadow-sm card-header sticky-top">
                        <span v-if="thread.stickyType == 'membertop'" class="badge badge-warning">会员置顶</span>
                        <span v-if="thread.stickyType == 'top'" class="badge badge-primary">置顶</span>
                        <span v-if="thread.isGood" class="badge badge-danger">精品</span>
                        <span v-if="thread.topicType == 'text'" class="badge badge-danger">文本话题</span>
                        <span v-if="thread.topicType == 'text'" class="badge badge-danger">文本话题</span><!-- TODO: fill unknown picture topic thread type -->
                        <h6 class="d-inline">{{ thread.title }}</h6>
                        <div class="float-right badge badge-light">
                            <router-link :to="{ name: 'tid', params: { tid: thread.tid.toString() } }" class="thread-list-show-only badge badge-pill badge-light">只看此贴</router-link>
                            <a class="badge badge-pill badge-light" :href="`https://tieba.baidu.com/p/${thread.tid}`" target="_blank"><i class="fas fa-link"></i></a>
                            <template v-for="latestReplier in [getUserData(thread.latestReplierUid)]">
                                <a class="badge badge-pill badge-light" href="#!"
                                   data-toggle="popover" data-placement="bottom" data-trigger="click hover" data-html="true"
                                   :data-title="`ID：${thread.tid}`"
                                   :data-content="`最后回复人：${latestReplier.displayName == null
                                       ? latestReplier.name
                                       : latestReplier.displayName + '（' + latestReplier.name + '）'}
                                       <br />最后回复时间：${thread.latestReplyTime}`">
                                    <i class="far fa-comment-dots"></i>
                                </a>
                            </template>
                            <a class="badge badge-pill badge-light" href="#!"
                               data-toggle="popover" data-placement="bottom" data-trigger="click hover" data-html="true"
                               :data-title="`ID：${thread.tid}`" :data-content="`收录时间：${thread.created_at}<br />最后更新：${thread.updated_at}`"><i class="fas fa-info"></i></a>
                            <span class="badge badge-pill badge-success">{{ thread.postTime }}</span>
                        </div>
                        <div>
                            <span class="badge badge-info"><i class="far fa-comment-alt"></i> {{ thread.replyNum }}</span>
                            <span class="badge badge-info"><i class="far fa-eye"></i> {{ thread.viewNum }}</span>
                            <span class="badge badge-info"><i class="fas fa-share-alt"></i> {{ thread.shareNum }}</span>
                            <span class="badge badge-info"><i class="far fa-thumbs-up"></i>agree：{{ thread.agreeInfo }}</span>
                            <span v-if="thread.zanInfo != null" class="badge badge-info"><i class="far fa-thumbs-up"></i>zan：{{ thread.zanInfo.num }}</span>
                            <span class="badge badge-info"><i class="fas fa-location-arrow"></i> {{ thread.locationInfo }}</span>
                        </div>
                    </div>
                    <template v-for="reply in thread.replies">
                        <div :id="reply.pid" class="reply-item" data-appear-top-offset="3000">
                            <div class="reply-title sticky-top card-header">
                                <div class="d-inline h5">
                                    <span class="badge badge-info">{{ reply.floor }}楼</span>
                                    <span v-if="reply.subReplyNum > 0" class="badge badge-info">{{ reply.subReplyNum }}条<i class="far fa-comment-dots"></i></span>
                                    <span>fold:{{ reply.isFold }}</span>
                                    <span>{{ reply.agreeInfo }}</span>
                                    <span>{{ reply.signInfo }}</span>
                                    <span>{{ reply.tailInfo }}</span>
                                    <span>{{ reply.agreeInfo }}</span>
                                    <span>{{ reply.signInfo }}</span>
                                    <span>{{ reply.tailInfo }}</span>
                                </div>
                                <div class="float-right badge badge-light">
                                    <router-link :to="{ name: 'pid', params: { pid: reply.pid.toString() } }" class="reply-list-show-only badge badge-pill badge-light">只看此楼</router-link>
                                    <a class="badge badge-pill badge-light" :href="`https://tieba.baidu.com/p/${reply.tid}?pid=${reply.pid}#${reply.pid}`" target="_blank"><i class="fas fa-link"></i></a>
                                    <a class="badge badge-pill badge-light" href="#!"
                                       data-toggle="popover" data-trigger="click hover" data-html="true"
                                       :data-title="`ID：${reply.pid}`" :data-content="`收录时间：${reply.created_at}<br />最后更新：${reply.updated_at}`"><i class="fas fa-info"></i></a>
                                    <span class="badge badge-pill badge-primary">{{ reply.postTime }}</span>
                                </div>
                            </div>
                            <div class="reply-info shadow-sm row bs-callout bs-callout-info">
                                <template v-for="author in [getUserData(reply.authorUid)]">
                                    <div class="reply-banner col-md-auto text-center">
                                        <div class="reply-user-info col sticky-top shadow-sm badge badge-light">
                                            <a class="d-block" :href="`http://tieba.baidu.com/home/main?un=${author.name}`" target="_blank">
                                                <img class="lazyload d-block mx-auto badge badge-light" width="100px" height="100px" :data-src="`https://himg.bdimg.com/sys/portrait/item/${author.avatarUrl}.jpg`" />
                                                <span>{{ author.displayName }}<br v-if="author.displayName != null" />{{ author.name }}</span>
                                            </a>
                                            <div v-if="author.uid == getUserData(thread.authorUid).uid" class="badge badge-pill badge-success">楼主</div>
                                            <div v-if="reply.authorManagerType != null">
                                                <span v-if="reply.authorManagerType == 'manager'" class="badge badge-danger">吧主</span>
                                                <span v-else-if="reply.authorManagerType == 'assist'" class="badge badge-info">小吧</span>
                                            </div>
                                            <div class="badge badge-pill badge-primary">Lv{{ reply.authorExpGrade }}</div>
                                        </div>
                                    </div>
                                </template>
                                <div class="reply-body col border-left">
                                    <div class="card-body p-3" v-html="reply.content"></div>
                                    <div v-if="reply.subReplies.length > 0" v-for="subReplyGroup in reply.subReplies"
                                         class="sub-reply-group card bs-callout bs-callout-success">
                                        <ul class="list-group list-group-flush">
                                            <li v-for="(subReply, index) in subReplyGroup" class="sub-reply-item list-group-item">
                                                <template v-for="author in [getUserData(subReply.authorUid)]">
                                                    <a v-if="subReplyGroup[index - 1] == undefined" class="sub-reply-user-info badge badge-light"
                                                       :href="`http://tieba.baidu.com/home/main?un=${author.name}`" target="_blank">
                                                        <img class="lazyload" width="25px" height="25px" :data-src="`https://himg.bdimg.com/sys/portrait/item/${author.avatarUrl}.jpg`" />
                                                        <span v-if="author.displayName == null">{{ author.name }}</span>
                                                        <span v-else>{{ author.displayName }}（{{ author.name }}）</span>
                                                        <div class="btn-group" role="group">
                                                            <button v-if="author.uid == getUserData(thread.authorUid).uid" type="button" class="badge btn btn-success">楼主</button>
                                                            <button v-else-if="author.uid == getUserData(reply.authorUid).uid" type="button" class="badge btn btn-info">层主</button>
                                                            <button v-if="subReply.authorManagerType == 'manager'" type="button" class="badge btn btn-danger">吧主</button>
                                                            <button v-else-if="subReply.authorManagerType == 'assist'" type="button" class="badge btn btn-info">小吧</button>
                                                            <button type="button" class="badge btn btn-primary">Lv{{ subReply.authorExpGrade }}</button>
                                                        </div>
                                                    </a>
                                                    <div class="float-right badge badge-light">
                                                        <a class="sub-reply-hide-link badge badge-pill badge-light" :href="`https://tieba.baidu.com/p/${subReply.tid}?pid=${subReply.spid}#${subReply.spid}`" target="_blank"><i class="fas fa-link"></i></a>
                                                        <a class="sub-reply-hide-link badge badge-pill badge-light" href="#!"
                                                           data-toggle="popover" data-trigger="click hover" data-html="true"
                                                           :data-title="`ID：${subReply.spid}`" :data-content="`收录时间：${subReply.created_at}<br />最后更新：${subReply.updated_at}`"><i class="fas fa-info"></i></a>
                                                        <span class="badge badge-pill badge-info">{{ subReply.postTime }}</span>
                                                    </div>
                                                </template>
                                                <div v-html="subReply.content"></div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="reply-list-next-page p-4">
                    <div class="row align-items-center">
                        <div class="col"><hr /></div>
                        <template v-for="page in [postsData.pages]">
                            <div class="w-auto">
                                <span v-if="page.currentPage == page.lastPage" class="h4">已经到底了~</span>
                                <button v-else @click="loadNewThreadsPage($event.currentTarget, page.currentPage + 1)" type="button" class="btn btn-secondary">
                                    <p class="h4">下一页</p>
                                    <small v-text="`剩 ${page.lastPage - page.currentPage} 页 共 ${page.lastPage} 页`"></small>
                                </button>
                            </div>
                        </template>
                        <div class="col"><hr /></div>
                    </div>
                </div>
            </div>
        </template>
        <template id="posts-list-pages-template">
            <div>
                <button type="button" class="floating-posts-nav-btn btn btn-light border"><i class="fas fa-bars"></i></button>
                <div class="floating-posts-nav border-left shadow-sm bg-light">
                    <template v-for="postsData in postsPages">
                        <template v-for="currentPage in [postsData.pages.currentPage]">
                            <nav :id="`posts-nav-page-${currentPage}`" class="posts-nav-page nav flex-column">
                                <a v-text="`第${currentPage}页`" href="#!"
                                   data-toggle="collapse" :data-target="`.posts-nav-thread[data-parent='#posts-nav-page-${currentPage}']`"
                                   aria-expanded="false" aria-controls="posts-nav" class="posts-nav-page-link border border-primary btn"></a>
                                <div v-for="thread in postsData.threads"
                                     :data-parent="`#posts-nav-page-${currentPage}`" class="posts-nav-thread border collapse">
                                    <a v-text="thread.title" href="#!"
                                       data-toggle="collapse" :id="`posts-nav-thread-${thread.tid}`"
                                       :data-target="`.posts-nav-reply[data-parent='#posts-nav-thread-${thread.tid}']`"
                                       aria-expanded="false" aria-controls="posts-nav-reply" class="posts-nav-thread-link border-bottom btn"></a>
                                    <nav class="nav flex-column">
                                        <a v-for="reply in thread.replies" v-text="`${reply.floor}L`"
                                           :data-parent="`#posts-nav-thread-${thread.tid}`"
                                           class="posts-nav-reply collapse btn" :href="`#${reply.pid}`"></a>
                                    </nav>
                                </div>
                            </nav>
                        </template>
                    </template>
                </div>
                <form @submit.prevent="submitQueryForm()" class="mt-3 query-form">
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryPostID">贴子ID</label>
                        <div id="queryPostID" class="input-group col">
                            <div class="input-group-prepend">
                                <span class="input-group-text">tid</span>
                            </div>
                            <select v-model="queryData.query.tidRange" data-param="tidRange" id="queryTidRange" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                            </select>
                            <input v-model="queryData.param.tid" data-param="tid" id="queryTid" type="number" class="form-control" placeholder="5000000000" aria-label="tid" />
                            <div class="input-group-prepend">
                                <span class="input-group-text">pid</span>
                            </div>
                            <select v-model="queryData.query.pidRange" data-param="pidRange" id="queryPidRange" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                            </select>
                            <input v-model="queryData.param.pid" data-param="pid" id="queryPid" type="number" class="form-control" placeholder="15000000000" aria-label="pid" />
                            <div class="input-group-prepend">
                                <span class="input-group-text">spid</span>
                            </div>
                            <select v-model="queryData.query.spidRange" data-param="spidRange" id="querySpidRange" class="col-1 form-control">
                                <option>&lt;</option>
                                <option>=</option>
                                <option>&gt;</option>
                            </select>
                            <input v-model="queryData.param.spid" data-param="spid" id="querySpid" type="number" class="form-control" placeholder="15000000000" aria-label="spid" />
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryFid">贴吧</label>
                        <div class="col-3 input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            </div>
                            <select v-model="queryData.query.fid" data-param="fid" id="queryFid" class="form-control">
                                <option value="undefined">未指定</option>
                                <option v-for="forum in forumsList" v-text="forum.name" :value="forum.fid"></option>
                            </select>
                        </div>
                        <label class="border-left col-1 col-form-label">贴子类型</label>
                        <div class="input-group my-auto col-4">
                            <div class="custom-checkbox custom-control custom-control-inline">
                                <input v-model="queryData.query.postType" data-param="postType" id="queryPostTypeThread" type="checkbox" value="thread" class="custom-control-input">
                                <label class="custom-control-label" for="queryPostTypeThread">主题贴</label>
                            </div>
                            <div class="custom-checkbox custom-control custom-control-inline">
                                <input v-model="queryData.query.postType" data-param="postType" id="queryPostTypeReply" type="checkbox" value="reply" class="custom-control-input">
                                <label class="custom-control-label" for="queryPostTypeReply">回复贴</label>
                            </div>
                            <div class="custom-checkbox custom-control custom-control-inline">
                                <input v-model="queryData.query.postType" data-param="postType" id="queryPostTypeSubReply" type="checkbox" value="subReply" class="custom-control-input">
                                <label class="custom-control-label" for="queryPostTypeSubReply">楼中楼</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryOrder">排序方式</label>
                        <div id="queryOrder" class="col-8 input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-sort-amount-down"></i></span>
                            </div>
                            <select v-model="queryData.query.orderBy" data-param="orderBy" id="queryOrderBy" class="form-control col">
                                <option value="default">默认（单贴查询按发贴时间正序；单吧/搜索查询倒序）</option>
                                <option value="postTime">发贴时间</option>
                                <optgroup label="贴子ID">
                                    <option value="tid">主题贴tid</option>
                                    <option value="pid">回复贴pid</option>
                                    <option value="spid">楼中楼spid</option>
                                </optgroup>
                            </select>
                            <select v-model="queryData.query.orderDirection" data-param="orderDirection" id="queryOrderDirection" class="col-4 form-control">
                                <option value="ASC">正序（从小/旧至大/新）</option>
                                <option value="DESC">倒序（从大/新至小/旧）</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <label class="col-2 col-form-label" for="queryPostTime">发贴时间</label>
                        <div id="queryPostTime" class="col-7 input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input v-model="queryData.query.postTimeStart" data-param="postTimeStart" id="queryPostTimeStart" type="datetime-local" class="custom-query-param form-control">
                            <div class="input-group-prepend">
                                <span class="input-group-text">至</span>
                            </div>
                            <input v-model="queryData.query.postTimeEnd" data-param="postTimeEnd" id="queryPostTimeEnd" type="datetime-local" class="custom-query-param form-control">
                        </div>
                    </div>
                    <div id="queryCustomQueryParamsCollapse" class="collapse">
                        <div class="card-body">
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryLatestReplyTime">最后回复时间</label>
                                <div id="queryLatestReplyTime" class="col-7 input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                    </div>
                                    <input v-model="queryData.query.latestReplyTimeStart" data-param="latestReplyTimeStart" id="queryLatestReplyTimeStart" type="datetime-local" class="custom-query-param form-control">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">至</span>
                                    </div>
                                    <input v-model="queryData.query.latestReplyTimeEnd" data-param="latestReplyTimeEnd" id="queryLatestReplyTimeEnd" type="datetime-local" class="custom-query-param form-control">
                                </div>
                                <small class="col align-self-center">仅主题贴</small>
                            </div>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadTitle">主题贴标题</label>
                                <div class="col-8 input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.threadTitleRegex" data-param="threadTitleRegex" id="queryThreadTitleRegex" type="checkbox" value="" class="custom-query-param custom-control-input">
                                                <label class="custom-control-label" for="queryThreadTitleRegex">正则</label>
                                            </div>
                                        </div>
                                    </div>
                                    <input v-model="queryData.query.threadTitle" data-param="threadTitle" id="queryThreadTitle" type="text" placeholder="模糊匹配 仅主题贴" class="custom-query-param form-control">
                                </div>
                                <small class="col align-self-center">仅主题贴</small>
                            </div>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryPostContent">内容关键词</label>
                                <div class="col-8 input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.postContentRegex" data-param="postContentRegex" id="queryPostContentRegex" type="checkbox" value="" class="custom-query-param custom-control-input">
                                                <label class="custom-control-label" for="queryPostContentRegex">正则</label>
                                            </div>
                                        </div>
                                    </div>
                                    <input v-model="queryData.query.postContent" data-param="postContent" id="queryPostContent" type="text" placeholder="模糊匹配 非正则下空格分割关键词" class="custom-query-param form-control">
                                </div>
                            </div>
                            <fieldset class="border rounded col-10 p-3 mb-2 form-inline form-row">
                                <legend class="h6 w-auto">用户信息 <small>主题贴下为楼主</small></legend>
                                <div class="mb-2 form-row">
                                    <label class="col-2 col-form-label">查询范围</label>
                                    <div class="custom-checkbox custom-control custom-control-inline">
                                        <input v-model="queryData.query.userType" data-param="userType" id="queryUserTypeAuthor" type="checkbox" value="author" class="custom-query-param custom-control-input">
                                        <label class="custom-control-label" for="queryUserTypeAuthor">发贴人</label>
                                    </div>
                                    <div class="custom-checkbox custom-control custom-control-inline">
                                        <input v-model="queryData.query.userType" data-param="userType" id="queryUserTypeLatestReplier" type="checkbox" value="latestReplier" class="custom-query-param custom-control-input">
                                        <label class="custom-control-label" for="queryUserTypeLatestReplier">最后回复人（仅主题贴）</label>
                                    </div>
                                    <label class="border-left col-2 col-form-lael" for="queryUserManagerType">吧务级别</label>
                                    <select v-model="queryData.query.userManagerType" data-param="userManagerType" id="queryUserManagerType" class="custom-query-param form-control col-2">
                                        <option value="default">所有</option>
                                        <option value="NULL">吧友</option>
                                        <option value="manager">吧主</option>
                                        <option value="assist">小吧主</option>
                                        <option value="voiceadmin">语音小编</option>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <label class="col-1 col-form-label" for="queryUserName">用户名</label>
                                    <input v-model="queryData.query.userName" data-param="userName" id="queryUserName" type="text" placeholder="n0099" class="custom-query-param form-control col-2">
                                    <label class="col-1 col-form-label" for="queryUserDisplayName">昵称</label>
                                    <input v-model="queryData.query.userDisplayName" data-param="userDisplayName" id="queryUserDisplayName" type="text" placeholder="神奇🍀" class="custom-query-param form-control col-2">
                                    <label class="col-1 col-form-label" for="queryUserExpGrade">等级</label>
                                    <div class="col-2 input-group">
                                        <select v-model="queryData.query.userExpGradeRange" data-param="userExpGradeRange" id="queryUserExpGradeRange" class="custom-query-param form-control">
                                            <option>&lt;</option>
                                            <option>=</option>
                                            <option>&gt;</option>
                                        </select>
                                        <input v-model="queryData.query.userExpGrade" data-param="userExpGrade" id="queryUserExpGrade" type="number" placeholder="18" class="custom-query-param form-control">
                                    </div>
                                    <label class="col-1 col-form-label" for="queryUserGender">性别</label>
                                    <select v-model="queryData.query.userGender" data-param="userGender" id="queryUserGender" class="custom-query-param form-control col-1">
                                        <option value="default">不限</option>
                                        <option value="0">未指定（显示为男）</option>
                                        <option value="1">男 ♂</option>
                                        <option value="2">女 ♀</option>
                                    </select>
                                </div>
                            </fieldset>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadProperty">主题贴属性</label>
                                <div id="queryThreadProperty" class="col-4 input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.threadProperty" data-param="threadProperty" id="queryThreadPropertyGood" type="checkbox" value="good" class="custom-query-param custom-control-input">
                                                <label class="text-danger font-weight-bold custom-control-label" for="queryThreadPropertyGood">精品</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <div class="custom-checkbox custom-control">
                                                <input v-model="queryData.query.threadProperty" data-param="threadProperty" id="queryThreadPropertySticky" type="checkbox" value="sticky" class="custom-query-param custom-control-input">
                                                <label class="text-primary font-weight-bold custom-control-label" for="queryThreadPropertySticky">置顶</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadReplyNumGroup">主题贴回复数</label>
                                <div id="queryThreadReplyNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.threadReplyNumRange" data-param="threadReplyNumRange" id="queryThreadReplyNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.threadReplyNum" data-param="threadReplyNum" id="queryThreadReplyNum" type="number" placeholder="100" class="custom-query-param form-control">
                                </div>
                                <label class="col-2 col-form-label" for="queryReplySubReplyNumGroup">楼中楼回复数</label>
                                <div id="queryReplySubReplyNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.replySubReplyNumRange" data-param="replySubReplyNumRange" id="queryReplySubReplyNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.replySubReplyNum" data-param="replySubReplyNum" id="queryReplySubReplyNum" type="number" placeholder="仅回复贴" class="custom-query-param form-control">
                                </div>
                            </div>
                            <div class="form-group form-row">
                                <label class="col-2 col-form-label" for="queryThreadViewNumGroup">主题贴查看量</label>
                                <div id="queryThreadViewNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.threadViewNumRange" data-param="threadViewNumRange" id="queryThreadViewNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.threadViewNum" data-param="threadViewNum" id="queryThreadViewNum" type="number" placeholder="100" class="custom-query-param form-control">
                                </div>
                                <label class="col-2 col-form-label" for="queryThreadShareNumGroup">主题贴分享量</label>
                                <div id="queryThreadShareNumGroup" class="col-3 input-group">
                                    <select v-model="queryData.query.threadShareNumRange" data-param="threadShareNumRange" id="queryThreadShareNumRange" class="col-4 form-control">
                                        <option>&lt;</option>
                                        <option>=</option>
                                        <option>&gt;</option>
                                    </select>
                                    <input v-model="queryData.query.threadShareNum" data-param="threadShareNum" id="queryThreadShareNum" type="number" placeholder="100" class="custom-query-param form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group form-row">
                        <button type="submit" class="btn btn-primary">查询</button>
                        <button class="ml-2 btn btn-light" type="button" data-toggle="collapse" data-target="#queryCustomQueryParamsCollapse" aria-expanded="false" aria-controls="queryCustomQueryParamsCollapse">搜索查询参数</button>
                    </div>
                </form>
                <posts-list v-for="(postsData, currentPostPage) in postsPages"
                            :key="`${currentPostPage + 1}@${$route.fullPath}`"
                            :posts-data="postsData"></posts-list>
                <loading-posts-placeholder v-if="loadingNewPosts"></loading-posts-placeholder>
            </div>
        </template>
        <div id="posts-list-pages">
            <router-view></router-view>
            <div id="first-loading-placeholder">
                <!-- use div instead of template to display div dom before vue loaded -->
                <div id="loading-posts-placeholder-template">
                    <div class="loading-posts-placeholder row align-items-center">
                        <div class="col"><hr /></div>
                        <div class="w-auto">
                            <div class="loading-icon mx-auto"></div>
                        </div>
                        <div class="col"><hr /></div>
                        <div class="w-100"></div>
                        <div class="col">
                            <div class="posts-list-placeholder"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="error-404-template">
                <hr />
                <div class="text-center" style="font-size: 8em">404</div>
            </div>
        </div>
    @endverbatim
@endsection

@section('script-after-container')
    @verbatim
        <script>
            'use strict';

            $('#error-404-template').hide();

            new Vue({ el: '#navbar' , data: { $$baseUrl, activeNav: 'query' } });
            Vue.component('loading-posts-placeholder', {
                template: '#loading-posts-placeholder-template'
            });

            const postsListComponent = Vue.component('posts-list', {
                template: '#posts-list-template',
                props: ['postsData'], // received from parent component
                data: function () {
                    return {
                        $$baseUrl
                    };
                },
                computed: {
                    getPerviousPageUrl: function() { // computed function will caching attr to ensure each posts-list's url will not updated after page param change
                        // generate an new absolute url with new page params which based on current route path
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
                            { iconInfo: [] },
                        ];
                    },
                    loadNewThreadsPage: function (eventDom, newPage) {
                        let pagingRouteName = this.$route.name.endsWith('+p') ? this.$route.name : this.$route.name + '+p';
                        this.$router.push({ name: pagingRouteName, params: { page: newPage.toString() }, query: this.$route.query }); // route params value should always be string
                    }
                }
            });

            const postsListPagesComponent = Vue.component('posts-list-pages', {
                template: '#posts-list-pages-template',
                data: function () {
                    return {
                        postsPages: [], // multi pages of posts list collection
                        loadingNewPosts: false,
                        forumsList: [],
                        queryData: [],
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
                        ]
                    };
                },
                methods: {
                    submitQueryForm: function () {
                        let queryParams = _.chain(this.$data.queryData.param)
                            .omit('page')
                            .omitBy(_.isEmpty) // omitBy will remove empty param values like empty string
                            .toPairs()
                            .map((param) => {
                                return _.fromPairs([param]); // convert { k:v, k:v } to [[k, v], [k ,v]]
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
                            if (Reflect.get(this.$data.queryData.param, param.replace('Range', '')) === undefined) {
                                Reflect.deleteProperty(customQueryParams, param);
                            }
                        });

                        let userInfoParams = [
                            'userManagerType',
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

                        this.$router.push({ path: `/query${queryParamsPath}`, query: customQueryParams });
                    },
                    loadPageData: function (routeParams, routeQueryStrings, shouldReplacePage) {
                        let preparePostsData = (data) => {
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

                        this.$data.loadingNewPosts = true;
                        let ajaxErrorCallback = () => {
                            this.$data.postsPages = []; // clear posts pages data will emit posts pages updated event
                            $('#error-404-template').show();
                        };
                        let ajaxStartTime = Date.now();
                        let queryQueryStrings = _.merge({}, routeParams, routeQueryStrings);
                        if (_.isEmpty(queryQueryStrings)) {
                            new Noty({ timeout: 3000, type: 'info', text: '请选择贴吧或/并输入查询参数'}).show();
                            ajaxErrorCallback();
                            return;
                        }
                        $.getJSON(`${$$baseUrl}/api/postsQuery?${$.param(queryQueryStrings)}`).done((jsonData) => {
                            jsonData = preparePostsData(jsonData);
                            let pagesInfo = jsonData.pages;

                            // is requesting new pages data on same query params or loading new data on different query params
                            if (shouldReplacePage) {
                                $('.posts-list *').off(); // remove all previous posts list children dom event to prevent re-hiding wrong reply item after load
                                this.$data.postsPages = [jsonData];
                            } else {
                                this.$data.postsPages.push(jsonData);
                            }
                            if (pagesInfo.totalItems === 0) {
                                ajaxErrorCallback();
                            }

                            new Noty({ timeout: 3000, type: 'success', text: `已加载第${pagesInfo.currentPage}页 ${pagesInfo.currentItems}条贴子 耗时${Date.now() - ajaxStartTime}ms`}).show();
                            this.changeDocumentTitle(this.$route);
                        }).fail((jqXHR) => {
                            ajaxErrorCallback();
                            new Noty({ timeout: 3000, type: 'error', text: `HTTP ${jqXHR.status} 耗时${Date.now() - ajaxStartTime}ms`}).show();
                            if (jqXHR.status === 400) {
                                new Noty({ timeout: 3000, type: 'warning', text: '请检查查询参数是否正确'}).show();
                            }
                        });
                    },
                    changeDocumentTitle: function (route, newPage = null, threadTitle = null) {
                        newPage = newPage || route.params.page || 1;
                        let forumName = `${this.$data.postsPages[0].forum.name}吧`;
                        if (route.params.tid != null) {
                            if (threadTitle == null) {
                                _.each(this.$data.postsPages, (item) => {
                                    threadTitle = _.find(item.threads, { tid: parseInt(route.params.tid) }).title;
                                });
                            }
                            document.title = `第${newPage}页 - 【${forumName}】${threadTitle} - 贴子查询 - 贴吧云监控`;
                        } else {
                            document.title = `第${newPage}页 - ${forumName} - 贴子查询 - 贴吧云监控`;
                        }
                    }
                },
                created: function () {
                    let customQueryParams = _.cloneDeep(this.$route.query);
                    let queryParams = _.omit(_.cloneDeep(this.$route.params), 'pathMatch'); // prevent store pathMatch property into params due to https://github.com/vuejs/vue-router/issues/2503
                    _.each(this.$data.arrayableCustomQueryParams, (arrayableParamName) => {
                        let arrayableParamValue = Reflect.get(customQueryParams, arrayableParamName);
                        if (arrayableParamValue != null && ! _.isArray(arrayableParamValue)) { // https://github.com/vuejs/vue-router/issues/1232
                            Reflect.set(customQueryParams, arrayableParamName, [arrayableParamValue]);
                            Reflect.set(this.$route.query, arrayableParamName, [Reflect.get(this.$route.query, arrayableParamName)]);
                            //this.$route.query.postType = [this.$route.query.postType]; // vue.$route should be unchangeable but in here we have to change it's query
                            /*this.$router.push({ query: _.mapValues(this.$route.query, (value, param) => {
                                    return param == 'postType' ? [value] : value;
                                })
                            });*/
                        }
                    });

                    this.$data.queryData = { query: customQueryParams, param: queryParams };
                    $.getJSON(`${$$baseUrl}/api/forumsList`).done((jsonData) => {
                        this.$data.forumsList = _.map(jsonData, (forum) => { // convert every fid to string to ensure fid params value type
                            forum.fid = forum.fid.toString();
                            return forum;
                        });
                        this.loadPageData(this.$data.queryData.param, this.$data.queryData.query, true); // wait for forums list finish loading
                    });
                },
                watch: {
                    loadingNewPosts: function (loadingNewPosts) {
                        if (loadingNewPosts) {
                            $('#error-404-template').hide();
                            $('.posts-list > .reply-list-next-page').remove();
                            $('#first-loading-placeholder').hide(); // use hide() instead of remove() to prevent vue can't find loading-posts-placeholder-template
                        }
                    },
                    postsPages: function () {
                        this.$data.loadingNewPosts = false;

                        this.$nextTick(() => { // run jquery on posts lists after vue components stop updating
                            $('.sub-reply-hide-link').hide();

                            let vue = this;

                            { // query params check
                                _.each(this.$data.customQueryParamsDefaultValue, (param) => { // set default params value on param form when it's not set
                                    if (Reflect.get(vue.$data.queryData.query, param.param) == null) {
                                        Reflect.set(vue.$data.queryData.query, param.param, param.default);
                                    }
                                });

                                let isRouteCustomQuery = vue.$route.query == null;
                                if (vue.$data.queryData.query.orderDirection == null) { // set default order direction which base on posts query type
                                    vue.$data.queryData.query.orderDirection = isRouteCustomQuery && _.isEmpty(_.omit(vue.$data.queryData.param, 'fid')) ? 'DESC' : 'ASC';
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
                                let checkRadioLikeCheckboxParamsGroupValue = (event) => { // ensure there's at least one post type checked
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

                                let checkUserInfoParamExcludingLatestReplier = () => {
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

                                let checkQueryParamsRequiredPostType = () => {
                                    //let postType = _.sortBy(vue.$data.queryData.query.postType); // sort post type array to compare without effect from order
                                    let queryPostTypes = vue.$data.queryData.query.postType;

                                    let paramsRequiredPostType = [
                                        { domID: 'queryUserTypeLatestReplier', postType: ['thread'] },
                                        { domID: 'queryThreadTitle', postType: ['thread'] },
                                        { domID: 'queryLatestReplyTimeStart', postType : ['thread'] },
                                        { domID: 'queryLatestReplyTimeEnd', postType : ['thread'] },
                                        { domID: 'queryThreadReplyNum', postType : ['thread'] },
                                        { domID: 'queryReplySubReplyNum', postType : ['reply'] },
                                        { domID: 'queryThreadViewNum', postType : ['thread'] },
                                        { domID: 'queryThreadShareNum', postType : ['thread'] },
                                        { domID: 'queryThreadPropertyGood', postType : ['thread'] },
                                        { domID: 'queryThreadPropertySticky', postType : ['thread'] },
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

                                let checkCustomQueryAvailable = () => {
                                    let isCustomQueryAvailable = $('#queryFid').prop('value') !== 'undefined'
                                        || ! _.isEmpty(vue.$data.queryData.param.tid)
                                        || ! _.isEmpty(vue.$data.queryData.param.pid)
                                        || ! _.isEmpty(vue.$data.queryData.param.spid);
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

                            { // reply items dom recycle and events
                                let registerEventsWithinReplyItems = () => {
                                    $('[data-toggle="popover"]').popover();
                                    // use jquery mouse hover event to prevent high cpu usage when using vue @mouseover event
                                    $('.sub-reply-item').hover((event) => {
                                        $(event.currentTarget).find('.sub-reply-hide-link').show();
                                    }, (event) => {
                                        $(event.currentTarget).find('.sub-reply-hide-link').hide();
                                    });
                                };
                                vue.$nextTick(() => {
                                    registerEventsWithinReplyItems();
                                });

                                let replyBodyAppearEventHandler = (event = null, eventTarget = null, customReplyItem = null) => {
                                    // listen reply body's appear event to ensure reply item really appeared for avoiding the offset effect
                                    _.throttle(() => {
                                        // replace browser url hash with current viewing reply id hash and it's page num
                                        let replyItem = customReplyItem || $(event.currentTarget).parents('.reply-item');
                                        let currentPage = replyItem.parents('.posts-list').data('page').toString();
                                        let threadTitle = replyItem.parents('.thread-item').data('title');
                                        let replyPid = replyItem.prop('id');
                                        if (replyItem.length !== 0 && currentPage != null) { // ensure reply item haven't deleted by dom recycling
                                            //replyItem.off('appear');
                                            if (customReplyItem != null && replyItem.hasClass('posts-list-placeholder')) {
                                                showReplyItem(replyItem, false); // display hided reply item immediately
                                            }

                                            vue.$router.replace({
                                                params: currentPage === "1" ? null : { page: currentPage },
                                                hash: `#${replyPid}`,
                                                query: vue.$route.query
                                            });
                                            vue.changeDocumentTitle(vue.$route, currentPage, threadTitle);

                                            let postsNav = $('.floating-posts-nav');
                                            let currentReplyNav = postsNav.find(`.posts-nav-reply[href="#${replyPid}"]`);
                                            // unselect other replies, threads and pages link
                                            postsNav.find('.posts-nav-reply').not(currentReplyNav).toggleClass('btn-info', false);
                                            postsNav.find('.posts-nav-thread').not(currentReplyNav.parents('.posts-nav-thread')).children('.posts-nav-thread-link').toggleClass('btn-info', false);
                                            postsNav.find('.posts-nav-page').not(currentReplyNav.parents('.posts-nav-page')).children('.posts-nav-page-link').toggleClass('btn-info', false);
                                            // select current replies, threads and pages link
                                            currentReplyNav.toggleClass('btn-info', true)
                                                .parent().siblings('.posts-nav-thread-link').toggleClass('btn-info', true)
                                                .parent().siblings('.posts-nav-page-link').toggleClass('btn-info', true);
                                            currentReplyNav[0].scrollIntoView();
                                            if (!currentReplyNav.is(':last-child')) {
                                                postsNav[0].scrollTop -= 50;
                                            }
                                        }
                                    }, 100, { leading: false })();
                                };

                                let showReplyItem = (replyItem, isInitialReply) => {
                                    if (! isInitialReply) {
                                        // uncomment sub dom and remove css height
                                        replyItem.css('height', '').html(replyItem.contents()[0].nodeValue);
                                        replyItem.toggleClass('posts-list-placeholder', false).fadeTo('slow', 1);
                                        registerEventsWithinReplyItems(); //re-register child dom's events
                                    }
                                    replyItem.find('.reply-body').appear().on('appear', replyBodyAppearEventHandler);
                                    replyItem.off('appear').appear().on('disappear', _.throttle((event) => {
                                        hideReplyItem($(event.currentTarget));
                                    }, 100, { leading: false }));
                                };

                                let hideReplyItem = (replyItem) => {
                                    // comment sub dom and set css height to keep scroll fixed
                                    replyItem.css('height', replyItem.height()).html(document.createComment(replyItem.html()));
                                    replyItem.toggleClass('posts-list-placeholder', true).fadeTo('slow', 0.5);
                                    replyItem.off('disappear').appear().on('appear', _.throttle((event) => {
                                        showReplyItem($(event.currentTarget), false);
                                    }, 100, { leading: false }));
                                };

                                // auto dom recycle and reproduce when dom (in)visible
                                $('.posts-list:last .reply-item')/*.off()*/.each((index, replyItem) => { // only work on last newly loaded page's reply item
                                    if (index > 5) {
                                        hideReplyItem($(replyItem)); // recycle all the other reply items dom
                                    } else {
                                        showReplyItem($(replyItem), true);
                                    }
                                });

                                $('.posts-nav-thread').collapse('show');
                                $('.posts-nav-reply').collapse('show').off('click').on('click', (event) => { // force trigger appear event when clicking reply navigation link
                                    replyBodyAppearEventHandler(null, null, $($(event.target).prop('hash')));
                                });
                                $('.floating-posts-nav-btn').off('click').on('click', () => {
                                    $('.floating-posts-nav').fadeToggle().find('.posts-nav-reply.btn-info')[0].scrollIntoView();
                                });

                                // scroll viewport to element anchor by url hash after posts list loaded
                                let urlHashReplyItemDom = this.$route.hash === '#!' ? null : $(this.$route.hash)[0];  // ignore #! shebang url hash
                                if (urlHashReplyItemDom != null) {
                                    urlHashReplyItemDom.scrollIntoView();
                                    replyBodyAppearEventHandler(null, null, $(urlHashReplyItemDom));
                                }
                            }
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
                        let shouldReplacePage = isQueryStringChanged || isRouteParamsExceptPageChanged;
                        let isPageAlreadyLoaded = to.query != null // if there's query string should always reload data
                            && ! isRouteParamsExceptPageChanged
                            && ! isQueryStringChanged
                            && _.filter(this.$data.postsPages, (item) => {
                                return item.pages.currentPage === (parseInt(to.params.page) || 1)
                            }).length !== 0;

                        if (! isPageAlreadyLoaded) {
                            this.loadPageData(to.params, to.query, shouldReplacePage);
                        } else {
                            next();
                        }
                    }
                    next();
                }
            });

            let postsListVue = new Vue({
                el: '#posts-list-pages',
                router: new VueRouter({
                    mode: 'history',
                    base: `${$$baseUrlDir}/`,
                    routes: [
                        {
                            name: 'postsQuery',
                            path: '/query',
                            component: postsListPagesComponent,
                            children: [
                                { name: 'postsQuery+p', path: 'page/:page' },
                                { name: 'tid', path: 'tid/:tid',  children: [{ name:'tid+p', path: 'page/:page' }] },
                                { name: 't+pid', path: 'tid/:tid/pid/:pid', children: [{ name:'t+pid+p', path: 'page/:page' }] },
                                { name: 't+sid', path: 'tid/:tid/spid/:spid', children: [{ name:'t+spid+p', path: 'page/:page' }] },
                                { name: 't+p+sid', path: 'tid/:tid/pid/:pid/spid/:spid', children: [{ name:'t+p+sid+p', path: 'page/:page' }] },
                                { name: 'pid', path: 'pid/:pid', children: [{ name:'pid+p', path: 'page/:page' }] },
                                { name: 'p+sid', path: 'pid/:pid/spid/:spid', children: [{ name:'p+sid+p', path: 'page/:page' }] },
                                { name: 'spid', path: 'spid/:spid', children: [{ name:'spid+p', path: 'page/:page' }] },
                                { name:  'customQuery', path: '*', query: '*', children: [{ name:'customQuery+p', path: 'page/:page' }]},
                            ]
                        }
                    ]
                })
            });
        </script>
    @endverbatim
@endsection