@extends('layout')
@include('module.bootstrapCallout')
@include('module.tiebaPostContentElement')
@include('module.vue.scrollList')
@include('module.vue.tiebaSelectUser')
@include('module.vue.antd')

@section('title', '贴子查询')

@section('style')
    <style>/* <error-placeholder> */
        .error-code {
            font-size: 4em;
        }
    </style>
    <style>/* <select-param> */
        .select-param.is-invalid {
            z-index: 1; /* let border overlaps other selects */
        }
        .select-param-first-row {
            border-top-left-radius: 0.25em !important;
        }
        .select-param-last-row {
            border-bottom-left-radius: 0.25em !important;
        }
    </style>
    <style>/* <query-form> */
        .query-param-row {
            margin-top: -1px;
        }
        .param-control-first-row {
            border-bottom-right-radius: 0;
        }
        .param-control-middle-row {
            border-bottom-right-radius: 0;
            border-top-right-radius: 0;
        }
        .param-control-last-row {
            border-top-right-radius: 0;
        }
        .param-input-group-text {
            background-color: unset;
        }

        .a-datetime-range {
            margin-left: -1px;
        }
        .ant-calendar-picker-input {
            height: 38px;
            border-bottom-left-radius: 0;
            border-top-left-radius: 0;
        }

        .add-param-button { /* fa-plus is wider than fa-times 3px */
            padding-left: 10px;
            padding-right: 11px;
        }
    </style>
    <style>/* <post-render-list> */
        .thread-item {
            margin-top: 1em;
        }
        .thread-title {
            padding: .75em 1em .5em 1em;
            background-color: #f2f2f2;
        }

        .reply-title {
            z-index: 1019;
            top: 62px;
            margin-top: .625em;
            border-top: 1px solid #ededed;
            border-bottom: 0;
            background: linear-gradient(rgba(237,237,237,1), rgba(237,237,237,.1));
        }
        .reply-info {
            padding: .625em;
            margin: 0;
            border-top: 0;
        }
        .reply-banner {
            padding-left: 0;
            padding-right: .5em;
        }
        .reply-body {
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

        .sub-reply-group {
            margin: 0 0 .25em .5em !important;
            padding: .25em;
        }
        .sub-reply-item {
            padding: .125em .125em .125em .5em;
        }
        .sub-reply-item > * {
            padding: .25em;
        }
        .sub-reply-user-info {
            font-size: 0.9em;
        }

        .post-time-badge {
            padding-left: 1em;
            padding-right: 1em;
        }
    </style>
    <style>/* <post-render-table> */
        .ant-table {
            width: fit-content;
        }

        .render-table-thread .ant-table { /* fixme: should only select first appeared child */
            width: auto;
            border: 1px solid #e8e8e8;
            border-radius: 4px 4px 0 0;
        }

        .ant-table td, .ant-table td *, .ant-table th {
            white-space: nowrap;
            font-family: Consolas, Courier New, monospace;
        }

        .ant-table-expand-icon-th, .ant-table-row-expand-icon-cell {
            width: 1px; /* any value other than 0px */
            min-width: unset;
            padding-left: 5px !important;
            padding-right: 0 !important;
        }
    </style>
    <style>/* <post-render-list> and <post-render-table> */
        .tieba-user-avatar-small {
            width: 25px;
            height: 25px;
        }
        .tieba-user-avatar-large {
            width: 90px;
            height: 90px;
        }
    </style>
    <style>/* <post-render-raw> */
        .render-raw {
            resize: both;
            height: 50em;
            font-family: Consolas, Courier New, monospace;
        }
    </style>
    <style>/* <post-page-previous-button> */
        .previous-page-button {
            position: relative;
            top: -1.5em;
            margin-bottom: -1em;
            padding-bottom: 4px;
            padding-top: 4px;
        }
    </style>
    <style>/* <loading-list-placeholder> */
        .post-item-placeholder {
            animation-name: post-item-placeholder;
            animation-play-state: running;
            animation-iteration-count: 9999;
            animation-duration: 3s;
        }
        @keyframes post-item-placeholder {
            0% {
                opacity: .5;
            }
            50% {
                opacity: 1;
            }
            100% {
                opacity: .5;
            }
        }
    </style>
    <style>/* <posts-nav> */
        .ant-menu {
            border: 0;
        }
        .ant-menu-item {
            height: auto !important; /* to show reply nav buttons under thread menu items */
            padding: 0 10px 0 32px !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            white-space: normal !important;
        }
        .ant-menu-item hr {
            margin: 7px 0 0 0;
        }
    </style>
    <style>/* <posts-query> */
        .posts-nav {
            padding: 0;
            overflow: hidden;
            border-right: 1px solid #ededed;
        }
        .posts-nav:hover {
            overflow-y: auto;
        }
        .posts-nav-collapse {
            padding: 2px;
            font-size: 1.3em;
            background-color: whitesmoke;
        }
        .post-render {
            padding-left: 10px;
        }
        .post-render-list-placeholder {
            padding: 0;
        }

        @media (max-width: 1200px) {
            .post-render {
                width: calc(100% - 15px); /* minus width of <posts-nav-collapse> to prevent it warps new row */
            }
            .posts-nav[aria-expanded=true] {
                display: block !important;
                position: sticky;
                top: 0;
                left: 0;
                width: fit-content;
                max-width: 35%;
            }
        }
        @media (min-width: 1200px) {
            .post-render {
                padding-left: 15px;
            }
            .post-render-list {
                max-width: 1000px;
            }
        }
        @media (min-width: 1400px) {
            .post-render-list-placeholder {
                display: block !important; /* only show right margin spaces when enough to prevent too narrow to display <posts-nav> */
            }
        }
    </style>
@endsection

@section('body')
    @verbatim
        <template id="input-text-match-param-template">
            <div class="input-group-append">
                <div :class="classes" class="input-group-text">
                    <div class="custom-radio custom-control custom-control-inline">
                        <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'implicit'" :id="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`"
                               :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="implicit" type="radio" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}Implicit-${paramIndex}`" class="custom-control-label">模糊</label>
                    </div>
                    <div class="custom-radio custom-control custom-control-inline">
                        <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'explicit'" :id="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`"
                               :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="explicit" type="radio" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}Explicit-${paramIndex}`" class="custom-control-label">精确</label>
                    </div>
                    <div class="custom-checkbox custom-control custom-control-inline">
                        <input @input="modelEvent('spaceSplit', $event.target.checked)" :checked="param.subParam.spaceSplit" :id="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`"
                               :disabled="param.subParam.matchBy === 'regex'" type="checkbox" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}SpaceSplit-${paramIndex}`" class="custom-control-label">空格分隔</label>
                    </div>
                    <div class="custom-radio custom-control">
                        <input @input="modelEvent('matchBy', $event.target.value)" :checked="param.subParam.matchBy === 'regex'" :id="`param${_.upperFirst(param.name)}Regex-${paramIndex}`"
                               :name="`param${_.upperFirst(param.name)}-${paramIndex}`" value="regex" type="radio" class="custom-control-input">
                        <label :for="`param${_.upperFirst(param.name)}Regex-${paramIndex}`" class="custom-control-label">正则</label>
                    </div>
                </div>
            </div>
        </template>
        <template id="input-numeric-param-template">
            <input v-if="param.subParam.range === 'IN'" @input="modelEvent($event.target.value)" :value="param.value"
                   :class="classes" :placeholder="placeholders.IN" :aria-label="param.name"
                   type="text" class="col form-control" required pattern="\d+(,\d+)+" />
            <input v-else-if="param.subParam.range === 'BETWEEN'" @input="modelEvent($event.target.value)" :value="param.value"
                   :class="classes" :placeholder="placeholders.BETWEEN" :aria-label="param.name"
                   type="text" class="col-3 form-control" required pattern="\d+,\d+" />
            <input v-else @input="modelEvent($event.target.value)" :value="param.value"
                   :class="classes" :placeholder="placeholders.number" :aria-label="param.name"
                   type="number" class="col-2 form-control" required />
        </template>
        <template id="select-range-template">
            <select @input="$emit('input', $event.target.value)" :value="value" class="col-1 custom-select form-control">
                <option>&lt;</option>
                <option>=</option>
                <option>&gt;</option>
                <option>IN</option>
                <option>BETWEEN</option>
            </select>
        </template>
        <template id="select-param-template">
            <select @change="$emit('param-change', $event)" class="col-2 custom-select form-control">
                <option selected value="add" disabled>New...</option>
                <optgroup v-for="(group, groupName) in paramsGroup" :label="groupName">
                    <option v-for="(paramDescription, paramName) in group" :selected="currentParam === paramName" :value="paramName">{{ paramDescription }}</option>
                </optgroup>
            </select>
        </template>
        <template id="query-form-template">
            <form @submit.prevent="submit()" class="mt-3">
                <div class="form-group form-row">
                    <label class="col-1 col-form-label" for="paramFid">贴吧</label>
                    <div class="col-3 input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                        </div>
                        <select v-model.number="uniqueParams.fid.value" :class="{ 'is-invalid': isFidInvalid }" id="paramFid" class="custom-select form-control">
                            <option value="NULL">未指定</option>
                            <option v-for="forum in forumList" :key="forum.fid" :value="forum.fid">{{ forum.name }}</option>
                        </select>
                    </div>
                    <label class="col-1 col-form-label text-center">贴子类型</label>
                    <div class="input-group my-auto col">
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypesThread" type="checkbox" value="thread" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypesThread">主题贴</label>
                        </div>
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypesReply" type="checkbox" value="reply" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypesReply">回复贴</label>
                        </div>
                        <div class="custom-checkbox custom-control custom-control-inline">
                            <input v-model="uniqueParams.postTypes.value" id="paramPostTypesSubReply" type="checkbox" value="subReply" class="custom-control-input">
                            <label class="custom-control-label" for="paramPostTypesSubReply">楼中楼</label>
                        </div>
                    </div>
                </div>
                <div class="form-group form-row">
                    <label class="col-1 col-form-label" for="paramOrder">排序方式</label>
                    <div id="paramOrder" class="col-8 input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-sort-amount-down"></i></span>
                        </div>
                        <select v-model="uniqueParams.orderBy.value" :class="{ 'is-invalid': isOrderByInvalid }" class="col custom-select form-control">
                            <option value="default">默认（按贴索引查询按发贴时间正序，按吧索引/搜索查询倒序）</option>
                            <option value="postTime">发贴时间</option>
                            <optgroup label="贴子ID">
                                <option value="tid">主题贴tid</option>
                                <option value="pid">回复贴pid</option>
                                <option value="spid">楼中楼spid</option>
                            </optgroup>
                        </select>
                        <select v-show="uniqueParams.orderBy.value !== 'default'" v-model="uniqueParams.orderBy.subParam.direction" class="col-6 custom-select form-control">
                            <option value="default">默认（按贴索引查询正序，按吧索引/搜索查询倒序）</option>
                            <option value="ASC">正序（从小到大，旧到新）</option>
                            <option value="DESC">倒序（从大到小，新到旧）</option>
                        </select>
                    </div>
                </div>
                <div v-for="(param, paramIndex) in params" class="query-param-row form-row">
                    <div class="input-group">
                        <button @click="deleteParam(paramIndex)" class="btn btn-link" type="button"><i class="fas fa-times"></i></button>
                        <select-param @param-change="changeParam(paramIndex, $event.target.value)" :current-param="param.name"
                                      :class="{
                                        'is-invalid': invalidParamsIndex.includes(paramIndex),
                                        'select-param-first-row': paramIndex === 0,
                                        'select-param-last-row': paramIndex === params.length - 1,
                                        'select-param': true
                                      }"></select-param>
                        <template v-if="_.includes(['tid', 'pid', 'spid'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)"
                                                 :placeholders="{
                                                    IN: param.name === 'tid' ? '5000000000,5000000001,5000000002,...' : '15000000000,15000000001,15000000002,...',
                                                    BETWEEN: param.name === 'tid' ? '5000000000,6000000000' : '15000000000,16000000000',
                                                    number: param.name === 'tid' ? 5000000000 : 15000000000
                                                 }"></input-numeric-param>
                        </template>
                        <template v-if="_.includes(['postTime', 'latestReplyTime'], param.name)">
                            <a-range-picker v-model="param.subParam.range" :locale="antd.locales.zh_CN.DatePicker" :show-time="true"
                                            format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DDTHH:mm" size="large" class="a-datetime-range"></a-range-picker>
                        </template>
                        <template v-if="_.includes(['threadTitle', 'postContent', 'authorName', 'authorDisplayName', 'latestReplierName', 'latestReplierDisplayName'], param.name)">
                            <input v-model="param.value" :placeholder="`${param.subParam.matchBy === 'implicit' ? '模糊' : (param.subParam.matchBy === 'explicit' ? '精确' : '正则')}匹配 空格${param.subParam.spaceSplit ? '不' : ''}分割关键词`" type="text" class="form-control" required>
                            <input-text-match-param v-model="params[paramIndex]" :param-index="paramIndex" :classes="paramRowLastDomClass(paramIndex, params)"></input-text-match-param>
                        </template>
                        <template v-if="_.includes(['threadViewNum', 'threadShareNum', 'threadReplyNum', 'replySubReplyNum'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)" :placeholders="{ IN: '100,101,102,...', BETWEEN: '100,200', number: 100 }"></input-numeric-param>
                        </template>
                        <template v-if="param.name === 'threadProperties'">
                            <div class="input-group-append">
                                <div class="param-input-group-text input-group-text">
                                    <div class="custom-checkbox custom-control">
                                        <input v-model="param.value" :id="`paramThreadPropertiesGood-${paramIndex}`" type="checkbox" value="good" class="custom-control-input">
                                        <label :for="`paramThreadPropertiesGood-${paramIndex}`" class="text-danger font-weight-normal custom-control-label">精品</label>
                                    </div>
                                </div>
                            </div>
                            <div class="input-group-append">
                                <div :class="paramRowLastDomClass(paramIndex, params)" class="param-input-group-text input-group-text">
                                    <div class="custom-checkbox custom-control">
                                        <input v-model="param.value" :id="`paramThreadPropertiesSticky-${paramIndex}`" type="checkbox" value="sticky" class="custom-control-input">
                                        <label :for="`paramThreadPropertiesSticky-${paramIndex}`" class="text-primary font-weight-normal custom-control-label">置顶</label>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-if="_.includes(['authorUid', 'latestReplierUid'], param.name)">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)"
                                                 :placeholders="{
                                                    IN: '4000000000,4000000001,4000000002,...',
                                                    BETWEEN: '4000000000,5000000000',
                                                    number: 4000000000
                                                 }"></input-numeric-param>
                        </template>
                        <template v-if="param.name === 'authorManagerType'">
                            <select value="NULL" v-model="param.value" class="col-2 form-control">
                                <option value="NULL">吧友</option>
                                <option value="manager">吧主</option>
                                <option value="assist">小吧主</option>
                                <option value="voiceadmin">语音小编</option>
                            </select>
                        </template>
                        <template v-if="_.includes(['authorGender', 'latestReplierGender'], param.name)">
                            <select v-model="param.value" class="col-2 form-control">
                                <option selected value="0">未设置（显示为男）</option>
                                <option value="1">男 ♂</option>
                                <option value="2">女 ♀</option>
                            </select>
                        </template>
                        <template v-if="param.name === 'authorExpGrade'">
                            <select-range v-model="param.subParam.range"></select-range>
                            <input-numeric-param v-model="params[paramIndex]" :classes="paramRowLastDomClass(paramIndex, params)" :placeholders="{ IN: '9,10,11,...', BETWEEN: '9,18', number: 18 }"></input-numeric-param>
                        </template>
                    </div>
                </div>
                <div class="mt-1 form-group form-row">
                    <button class="add-param-button disabled btn btn-link" type="button"><i class="fas fa-plus"></i></button>
                    <select-param @param-change="addParam($event)"></select-param>
                </div>
                <div class="form-group form-row">
                    <button :disabled="isRequesting" type="submit" class="btn btn-primary">
                        查询 <span v-show="isRequesting" class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
                    </button>
                    <button class="ml-2 disabled btn btn-text" type="button">{{ currentQueryType() === 'fid' ? '按吧索引查询' : (currentQueryType() === 'postID' ? '按贴索引查询' : (currentQueryType() === 'search' ? '搜索查询' : '空查询')) }}</button>
                </div>
            </form>
        </template>
        <template id="post-thread-tag-template">
            <div class="btn-group" role="group">
                <button v-if="thread.stickyType === 'membertop'" class="badge btn btn-warning">会员置顶</button>
                <button v-if="thread.stickyType === 'top'" class="badge btn btn-primary">置顶</button>
                <button v-if="thread.isGood" class="badge btn btn-danger">精品</button>
                <button v-if="thread.topicType === 'text'" class="badge btn btn-primary">文本话题</button>
                <button v-if="thread.topicType === ''" class="badge btn btn-primary">图片话题</button><!-- TODO: fill unknown picture topic thread type -->
            </div>
        </template>
        <template id="post-user-tag-template">
            <div class="btn-group" role="group">
                <button v-if="userInfo.uid === $data.$getUserInfo(parentUid.thread).uid" type="button" class="badge btn btn-success">楼主</button>
                <button v-else-if="userInfo.uid === $data.$getUserInfo(parentUid.reply).uid" type="button" class="badge btn btn-info">层主</button>
                <button v-if="userInfo.managerType === 'manager'" type="button" class="badge btn btn-danger">吧主</button>
                <button v-else-if="userInfo.managerType === 'assist'" type="button" class="badge btn btn-info">小吧</button>
                <button v-else-if="userInfo.managerType === 'voiceadmin'" type="button" class="badge btn btn-info">语音小编</button>
                <button v-if="userInfo.expGrade !== undefined" type="button" class="badge btn btn-primary">Lv{{ userInfo.expGrade }}</button>
            </div>
        </template>
        <template id="post-render-list-template">
            <div class="pb-3">
                <div v-for="thread in posts.threads" :id="`t${thread.tid}`" class="thread-item card">
                    <div class="thread-title shadow-sm card-header sticky-top">
                        <post-thread-tag :thread="thread"></post-thread-tag>
                        <h6 class="d-inline">{{ thread.title }}</h6>
                        <div class="float-right badge badge-light">
                            <router-link :to="{ name: 'tid', params: { tid: thread.tid } }" class="badge badge-pill badge-light">只看此贴</router-link>
                            <a :href="$data.$$getTiebaPostLink(thread.tid)" target="_blank" class="badge badge-pill badge-light"><i class="fas fa-link"></i></a>
                            <a :data-tippy-content="`<h6>tid：${thread.tid}</h6><hr />
                                最后回复人：${renderUsername(thread.latestReplierUid)}<br />
                                最后回复时间：${moment(thread.latestReplyTime).fromNow()}（${thread.latestReplyTime}）<br />
                                首次收录时间：${moment(thread.created_at).fromNow()}（${thread.created_at}）<br />
                                最后更新时间：${moment(thread.updated_at).fromNow()}（${thread.updated_at}）`"
                               class="badge badge-pill badge-light">
                                <i class="fas fa-info"></i>
                            </a>
                            <span :data-tippy-content="thread.postTime" class="post-time-badge badge badge-pill badge-success">{{ moment(thread.postTime).fromNow() }}</span>
                        </div>
                        <div class="mt-1">
                            <span data-tippy-content="回复量" class="badge badge-info">
                                <i class="far fa-comment-alt"></i> {{ thread.replyNum }}
                            </span>
                                <span data-tippy-content="阅读量" class="badge badge-info">
                                <i class="far fa-eye"></i> {{ thread.viewNum }}
                            </span>
                                <span data-tippy-content="分享量" class="badge badge-info">
                                <i class="fas fa-share-alt"></i> {{ thread.shareNum }}
                            </span>
                                <span v-if="thread.agreeInfo !== null" data-tippy-content="赞踩量" class="badge badge-info">
                                <i class="far fa-thumbs-up"></i>{{ thread.agreeInfo.agree_num }}
                                <i class="far fa-thumbs-down"></i>{{ thread.agreeInfo.disagree_num }}
                            </span>
                                <span v-if="thread.zanInfo !== null" :data-tippy-content="`
                                    点赞量：${thread.zanInfo.num}<br />
                                    最后点赞时间：${moment.unix(thread.zanInfo.last_time).fromNow()}（${moment.unix(thread.zanInfo.last_time).format()}）<br />
                                    近期点赞用户：${thread.zanInfo.user_id_list}<br />`"
                                      class="badge badge-info"><!-- todo: fetch users info in zanInfo.user_id_list -->
                                <i class="far fa-thumbs-up"></i> 旧版客户端赞
                            </span>
                                <span data-tippy-content="发贴位置" class="badge badge-info">
                                <i class="fas fa-location-arrow"></i> {{ thread.location }} <!-- todo: unknown json struct -->
                            </span>
                        </div>
                    </div>
                    <div v-for="reply in thread.replies" v-observe-visibility="{ callback: replyObserved, throttle: 500 }" :id="reply.pid">
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
                            -->
                            </div>
                            <div class="float-right badge badge-light">
                                <router-link :to="{ name: 'pid', params: { pid: reply.pid } }" class="badge badge-pill badge-light">只看此楼</router-link>
                                <a :href="$data.$$getTiebaPostLink(reply.tid, reply.pid)" target="_blank" class="badge badge-pill badge-light"><i class="fas fa-link"></i></a>
                                <a :data-tippy-content="`
                                    <h6>pid：${reply.pid}</h6><hr />
                                    首次收录时间：${moment(reply.created_at).fromNow()}（${reply.created_at}）<br />
                                    最后更新时间：${moment(reply.updated_at).fromNow()}（${reply.updated_at}）`"
                                   class="badge badge-pill badge-light">
                                    <i class="fas fa-info"></i>
                                </a>
                                <span :data-tippy-content="reply.postTime" class="post-time-badge badge badge-pill badge-primary">{{ moment(reply.postTime).fromNow() }}</span>
                            </div>
                        </div>
                        <div class="reply-info shadow-sm row bs-callout bs-callout-info">
                            <div v-for="author in [$data.$getUserInfo(reply.authorUid)]" class="reply-banner text-center col-auto">
                                <div class="reply-user-info sticky-top shadow-sm badge badge-light">
                                    <a :href="$data.$$getTiebaUserLink(author.name)" target="_blank" class="d-block">
                                        <img :data-src="$data.$$getTiebaUserAvatarUrl(author.avatarUrl)" class="tieba-user-avatar-large lazyload d-block mx-auto badge badge-light"/>
                                        <span>
                                        {{ author.name }}
                                        <br v-if="author.displayName !== null && author.name !== null" />
                                        {{ author.displayName }}
                                    </span>
                                    </a>
                                    <post-user-tag :parent-uid="{ thread: thread.authorUid }" :user-info="{ uid: reply.authorUid, managerType: reply.authorManagerType, expGrade: reply.authorExpGrade }" :users-info-source="posts.users"></post-user-tag>
                                </div>
                            </div>
                            <div class="reply-body col border-left">
                                <div class="p-2" v-html="reply.content"></div>
                                <template v-if="reply.subReplies.length > 0">
                                    <div v-for="subReplyGroup in reply.subReplies" class="sub-reply-group bs-callout bs-callout-success">
                                        <ul class="list-group list-group-flush">
                                            <li v-for="(subReply, subReplyIndex) in subReplyGroup" @mouseenter="hoveringSubReplyItem = subReply.spid" @mouseleave="hoveringSubReplyItem = null" class="sub-reply-item list-group-item">
                                                <template v-for="author in [$data.$getUserInfo(subReply.authorUid)]">
                                                    <a v-if="subReplyGroup[subReplyIndex - 1] === undefined" :href="$data.$$getTiebaUserLink(author.name)" target="_blank" class="sub-reply-user-info badge badge-light">
                                                        <img :data-src="$data.$$getTiebaUserAvatarUrl(author.avatarUrl)" class="tieba-user-avatar-small lazyload" />
                                                        <span>{{ renderUsername(subReply.authorUid) }}</span>
                                                        <post-user-tag :parent-uid="{ thread: thread.authorUid, reply: reply.authorUid }" :user-info="{ uid: subReply.authorUid, managerType: subReply.authorManagerType, expGrade: subReply.authorExpGrade }" :users-info-source="posts.users"></post-user-tag>
                                                    </a>
                                                    <div class="float-right badge badge-light">
                                                        <div :class="{
                                                                'd-none': hoveringSubReplyItem !== subReply.spid,
                                                                'd-inline': hoveringSubReplyItem === subReply.spid
                                                             }"><!-- fixme: high cpu usage due to js evaling while quickly emitting hover event -->
                                                            <a :href="$data.$$getTiebaPostLink(subReply.tid, null, subReply.spid)" target="_blank" class="badge badge-pill badge-light"><i class="fas fa-link"></i></a>
                                                            <a :data-tippy-content="`
                                                                <h6>spid：${subReply.spid}</h6><hr />
                                                                首次收录时间：${moment(subReply.created_at).fromNow()}（${subReply.created_at}）<br />
                                                                最后更新时间：${moment(subReply.created_at).fromNow()}（${subReply.updated_at}）`"
                                                               class="badge badge-pill badge-light">
                                                                <i class="fas fa-info"></i>
                                                            </a>
                                                        </div>
                                                        <span :data-tippy-content="subReply.postTime" class="post-time-badge badge badge-pill badge-info">{{ moment(subReply.postTime).fromNow() }}</span>
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
                </div>
            </div>
        </template>
        <template id="post-render-table-template">
            <div class="container-flow pb-2">
                <a-table :columns="threadColumns" :data-source="threads" :default-expand-all-rows="true" :expand-row-by-click="true" :pagination="false" :scroll="{ x: true }" size="middle" class="render-table-thread">
                    <template slot="tid" slot-scope="text, record">
                        <router-link :to="{ name: 'tid', params: { tid: record.tid } }">{{ record.tid }}</router-link>
                    </template>
                    <template slot="firstPid" slot-scope="text, record">
                        <router-link :to="{ name: 'pid', params: { pid: record.firstPid } }">{{ record.firstPid }}</router-link>
                    </template>
                    <template slot="titleWithTag" slot-scope="text, record">
                        <post-thread-tag :thread="record"></post-thread-tag>
                        <span>{{ record.title }}</span>
                    </template>
                    <template slot="authorInfo" slot-scope="text, record">
                        <a :href="$data.$$getTiebaUserLink($data.$getUserInfo(record.authorUid).name)">
                            <img class="tieba-user-avatar-small lazyload" :data-src="$data.$$getTiebaUserAvatarUrl($data.$getUserInfo(record.authorUid).avatarUrl)" /> {{ renderUsername(record.authorUid) }}
                        </a>
                        <post-user-tag :user-info="{ uid: record.authorUid, managerType: record.authorManagerType }" :users-info-source="posts.users"></post-user-tag>
                    </template>
                    <template slot="latestReplierInfo" slot-scope="text, record">
                        <a :href="$data.$$getTiebaUserLink($data.$getUserInfo(record.latestReplierUid).name)">
                            <img class="tieba-user-avatar-small lazyload" :data-src="$data.$$getTiebaUserAvatarUrl($data.$getUserInfo(record.authorUid).avatarUrl)" /> {{ renderUsername(record.authorUid) }}
                        </a>
                        <post-user-tag :user-info="{ uid: record.latestReplierUid }" :users-info-source="posts.users"></post-user-tag>
                    </template>
                    <template slot="expandedRowRender" slot-scope="record">
                        <span v-if="threadsReply[record.tid] === undefined">无子回复帖</span>
                        <a-table v-else :columns="replyColumns" :data-source="threadsReply[record.tid]" :default-expand-all-rows="true" :expand-row-by-click="true" :pagination="false" size="middle">
                            <template v-for="thread in [record]" slot="authorInfo" slot-scope="text, record">
                                <a :href="$data.$$getTiebaUserLink($data.$getUserInfo(record.authorUid).name)">
                                    <img class="tieba-user-avatar-small lazyload" :data-src="$data.$$getTiebaUserAvatarUrl($data.$getUserInfo(record.authorUid).avatarUrl)" /> {{ renderUsername(record.authorUid) }}
                                </a>
                                <post-user-tag :parent-uid="{ thread: thread.authorUid }" :user-info="{ uid: record.authorUid, managerType: record.authorManagerType, expGrade: record.authorExpGrade }" :users-info-source="posts.users"></post-user-tag>
                            </template>
                            <template slot="expandedRowRender" slot-scope="record">
                                <div :is="repliesSubReply[record.pid] === undefined ? 'span' : 'p'" v-html="record.content"></div>
                                <a-table v-if="repliesSubReply[record.pid] !== undefined" :columns="subReplyColumns" :data-source="repliesSubReply[record.pid]" :default-expand-all-rows="true" :expand-row-by-click="true" :pagination="false" size="middle">
                                    <template v-for="reply in [record]" slot="authorInfo" slot-scope="text, record">
                                        <a :href="$data.$$getTiebaUserLink($data.$getUserInfo(record.authorUid).name)">
                                            <img class="tieba-user-avatar-small lazyload" :data-src="$data.$$getTiebaUserAvatarUrl($data.$getUserInfo(record.authorUid).avatarUrl)" /> {{ renderUsername(record.authorUid) }}
                                        </a>
                                        <post-user-tag :parent-uid="{ thread: _.find(posts.threads, { tid: reply.tid }).authorUid, reply: reply.authorUid }" :user-info="{ uid: record.authorUid, managerType: record.authorManagerType, expGrade: record.authorExpGrade }" :users-info-source="posts.users"></post-user-tag>
                                    </template>
                                    <template slot="expandedRowRender" slot-scope="record">
                                        <span v-html="record.content"></span>
                                    </template>
                                </a-table>
                            </template>
                        </a-table>
                    </template>
                </a-table>
            </div>
        </template>
        <template id="post-render-raw-template">
            <textarea :value="JSON.stringify(posts)" class="render-raw border col"></textarea>
        </template>
        <template id="posts-nav-template">
            <a-menu :force-sub-menu-render="true" mode="inline" class="vh-100">
                <a-sub-menu v-for="posts in pages" :title="`第${posts.pages.currentPage}页`" :key="`page-${posts.pages.currentPage}`">
                    <a-menu-item v-for="thread in posts.threads" :key="`thread-${thread.tid}`" :title="thread.title">
                        <a :href="`#t${thread.tid}`">{{ thread.title }}</a><!-- fixme: cannot use <router-link> here since history.pushState() won't auto scroll viewport into dom which id is same with hash -->
                        <div class="d-block btn-group" role="group">
                            <a v-for="reply in thread.replies" v-scroll-to-reply="reply.pid === parseInt($route.hash.substr(1))" :href="`#${reply.pid}`" :class="{
                                    'btn': true,
                                    'btn-info': reply.pid === parseInt($route.hash.substr(1)),
                                    'btn-light': reply.pid !== parseInt($route.hash.substr(1))
                               }">
                                {{ reply.floor }}L
                            </a>
                        </div>
                        <hr />
                    </a-menu-item>
                </a-sub-menu>
            </a-menu>
        </template>
        <template id="post-page-previous-button-template">
            <div v-scroll-to-page="pageInfo.currentPage === scrollToPage.value" class="mt-2 p-2 row align-items-center">
                <div class="col"><hr /></div>
                <div class="w-auto">
                    <div class="p-2 badge badge-light">
                        <button v-if="pageInfo.currentPage > 1" @click="loadPage(pageInfo.currentPage - 1)" class="previous-page-button btn btn-primary" type="button">上一页</button>
                        <p class="h4">第 {{ pageInfo.currentPage }} 页</p>
                        <span class="small">第 {{ pageInfo.firstItem }}~{{ pageInfo.firstItem + pageInfo.currentItems - 1 }} 条</span>
                    </div>
                </div>
                <div class="col"><hr /></div>
            </div>
        </template>
        <template id="post-page-next-button-template">
            <div class="p-4">
                <div class="row align-items-center">
                    <div class="col"><hr /></div>
                    <div class="w-auto">
                        <button @click="loadPage(currentPage + 1)" class="btn btn-secondary" type="button">
                            <span class="h4">下一页</span>
                        </button>
                    </div>
                    <div class="col"><hr /></div>
                </div>
            </div>
        </template>
        <template id="posts-query-template">
            <div>
                <div class="container">
                    <query-form @query="query($event)" :forum-list="forumList" ref="queryForm"></query-form>
                    <p>当前页数：{{ currentRoutesPage }}</p>
                    <a-menu v-show="! _.isEmpty(postPages)" v-model="selectingRenderType" mode="horizontal">
                        <a-menu-item key="list">列表视图</a-menu-item>
                        <a-menu-item key="table">表格视图</a-menu-item>
                        <a-menu-item key="raw">RAW</a-menu-item>
                    </a-menu>
                </div>
                <div v-show="! _.isEmpty(postPages)" class="container-fluid">
                    <div class="justify-content-center row">
                        <div :aria-expanded="postsNavExpanded" class="posts-nav vh-100 sticky-top d-none d-xl-block col-xl">
                            <posts-nav :pages="postPages"></posts-nav>
                        </div>
                        <a @click="postsNavExpanded = ! postsNavExpanded" class="posts-nav-collapse shadow-sm vh-100 sticky-top align-items-center d-flex d-xl-none col col-auto">
                            <i v-show="postsNavExpanded" class="fas fa-angle-left"></i>
                            <i v-show="! postsNavExpanded" class="fas fa-angle-right"></i>
                        </a>
                        <div :class="{
                                'post-render': true,
                                'post-render-list': renderType === 'list',
                                'col': true,
                                'col-xl-auto': true,
                                'col-xl-10': renderType !== 'list' // let render, except <post-render-list>, takes over right margin spaces, aka .post-render-list-placeholder
                             }">
                            <template v-for="(posts, pageIndex) in postPages">
                                <post-page-previous-button @load-page="loadPage($event)" :page-info="posts.pages"></post-page-previous-button>
                                <post-render-list v-if="renderType === 'list'"
                                                  v-observe-visibility="{ callback: (isVisible) => isVisible ? changeTitle(posts.pages.currentPage) : null, throttle: 500 }"
                                                  :key="posts.pages.currentPage" :initial-posts="posts"></post-render-list>
                                <post-render-table v-else-if="renderType === 'table'"
                                                   v-observe-visibility="{ callback: (isVisible) => isVisible ? changeTitle(posts.pages.currentPage) : null, throttle: 500 }"
                                                   :key="posts.pages.currentPage" :posts="posts"></post-render-table>
                                <post-render-raw v-else-if="renderType === 'raw'"
                                                 v-observe-visibility="{ callback: (isVisible) => isVisible ? changeTitle(posts.pages.currentPage) : null, throttle: 500 }"
                                                 :key="posts.pages.currentPage" :posts="posts"></post-render-raw>
                                <post-page-next-button v-if="! $refs.queryForm.$data.isRequesting && pageIndex === postPages.length - 1" @load-page="loadPage($event)" :current-page="posts.pages.currentPage"></post-page-next-button>
                            </template>
                        </div>
                        <div v-show="renderType === 'list'" class="post-render-list-placeholder d-none col-xl"></div>
                    </div>
                </div>
                <div class="container">
                    <error-placeholder v-if="queryError !== null" :error="queryError"></error-placeholder>
                    <loading-list-placeholder v-show="($refs.queryForm || { $data: { isRequesting: false } }).$data.isRequesting"></loading-list-placeholder>
                </div>
            </div>
        </template>
        <template id="error-placeholder-template">
            <div class="text-center">
                <hr />
                <p class="error-code">{{ error.code }}</p>
                <p v-html="error.info"></p>
            </div>
        </template>
        <div id="root-vue">
            <router-view></router-view>
            <!-- use div instead of template to display dom before vue loaded, v-show=false to hide dom after vue finish load -->
            <div v-show="false" id="loading-list-placeholder-template" class="container">
                <div class="loading-list-placeholder row align-items-center">
                    <div class="col"><hr /></div>
                    <div class="w-auto">
                        <div class="loading-icon mx-auto"></div>
                    </div>
                    <div class="col"><hr /></div>
                    <div class="w-100"></div>
                    <div class="col">
                        <div class="post-item-placeholder">
    @endverbatim
                            <img src="{{ asset('/img/tombstone-post-list.svg') }}" />
    @verbatim
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
            $$initialNavBar('postMulti');

            window.$previousPostsQueryAjax = undefined;
            window.$sharedDataBindings = {
                scrollToPage: { value: 0 }
            };
            window.$getUserInfo = (dataSource) => (uid) => // curry invoke
                _.find(dataSource, { uid }) || { // thread latest replier uid might be unknown
                    id: 0,
                    uid: 0,
                    name: '未知用户',
                    displayName: null,
                    avatarUrl: null,
                    gender: 0,
                    fansNickname: null,
                    iconInfo: []
                };

            const postsNavComponent = Vue.component('posts-nav', {
                template: '#posts-nav-template',
                props: {
                    pages: { type: Array, required: true }
                },
                directives: {
                    'scroll-to-reply' (el, binding, vnode) {
                        if (binding.value) {
                            el.scrollIntoView();
                            if (! $(el).is(':last-child')) {
                                $('.posts-nav')[0].scrollTop -= 100; // scroll y axis of <posts-nav> by -100px after reply link scrolled into top of <posts-nav> for show thread title
                            }
                        }
                    }
                }
            });

            const postThreadTagComponent = Vue.component('post-thread-tag', {
                template: '#post-thread-tag-template',
                props: {
                    thread: { type: Object, required: true }
                }
            });

            const postUserTagComponent = Vue.component('post-user-tag', {
                template: '#post-user-tag-template',
                props: {
                    usersInfoSource: { type: Array, required: true },
                    userInfo: { type: Object, required: true },
                    parentUid: {
                        type: Object,
                        default () {
                            return {};
                        }
                    }
                },
                data () {
                    return {
                        $getUserInfo: window.$getUserInfo(this.$props.usersInfoSource)
                    };
                }
            });

            const postPagePreviousButtonComponent = Vue.component('post-page-previous-button', {
                template: '#post-page-previous-button-template',
                props: {
                    pageInfo: { type: Object, required: true }
                },
                data () {
                    return {
                        scrollToPage: window.$sharedDataBindings.scrollToPage
                    }
                },
                directives: {
                    'scroll-to-page' (el, binding, vnode) {
                        if (binding.value) {
                            vnode.context.$nextTick(() => el.scrollIntoView()); // when page haven't been requested before, new vueInstance.$el is not ready
                            window.$sharedDataBindings.scrollToPage.value = 0; // reset for next time scroll with same scrollToPage
                        }
                    }
                },
                methods: {
                    loadPage (pageNum) {
                        this.$data.scrollToPage.value = pageNum;
                        this.$emit('load-page', pageNum);
                    }
                }
            });

            const postPageNextButtonComponent = Vue.component('post-page-next-button', {
                template: '#post-page-next-button-template',
                props: {
                    currentPage: { type: Number, required: true }
                },
                data () {
                    return {
                        scrollToPage: window.$sharedDataBindings.scrollToPage
                    }
                },
                methods: {
                    loadPage (pageNum) {
                        this.$data.scrollToPage.value = pageNum;
                        this.$emit('load-page', pageNum);
                    }
                }
            });

            const postRenderListComponent = Vue.component('post-render-list', {
                template: '#post-render-list-template',
                props: {
                    initialPosts: { type: Object, required: true }
                },
                data () {
                    return {
                        moment,
                        $$getTiebaUserLink,
                        $$getTiebaPostLink,
                        $$getTiebaUserAvatarUrl,
                        $getUserInfo: window.$getUserInfo(this.$props.initialPosts.users),
                        hoveringSubReplyItem: 0 // for display item's right floating hide buttons
                    };
                },
                computed: {
                    posts () {
                        let postsData = _.cloneDeep(this.$props.initialPosts); // prevent mutates props in other post renders
                        postsData.threads = _.map(postsData.threads, (thread) => {
                            thread.replies = _.map(thread.replies, (reply) => {
                                reply.subReplies = _.reduce(reply.subReplies, (groupedSubReplies, subReply, index, subReplies) => {
                                    // group sub replies item by continuous and same author info
                                    let previousSubReply = subReplies[index - 1];
                                    if (previousSubReply !== undefined
                                        && subReply.authorUid === previousSubReply.authorUid
                                        && subReply.authorManagerType === previousSubReply.authorManagerType
                                        && subReply.authorExpGrade === previousSubReply.authorExpGrade) {
                                        _.last(groupedSubReplies).push(subReply); // append to last group
                                    } else {
                                        groupedSubReplies.push([subReply]); // new group
                                    }
                                    return groupedSubReplies;
                                }, []);
                                return reply;
                            });
                            return thread;
                        });
                        return postsData;
                    }
                },
                mounted () {
                    this.$nextTick(() => { // initial dom event after all dom and child components rendered
                        $$registerTippy();
                        $$registerTiebaImageZoomEvent();
                    });
                },
                beforeDestroy () {
                    // this.$el is already unmounted from document while beforeDestroy()
                    $$registerTippy(this.$el, true);
                    $$registerTiebaImageZoomEvent(this.$el, true);
                },
                methods: {
                    replyObserved (isVisible, observer) {
                        if (isVisible) { // fixme: force reflows (Schedule Style Recalculation) with long execute time triggered when quickly scrolling page
                            // this.$router.replace({ hash: `#${$(observer.target).prop('id') }` });
                        }
                    },
                    renderUsername: function (uid) {
                        let user = this.$data.$getUserInfo(uid);
                        let name = user.name;
                        let displayName = user.displayName;
                        if (name === null) {
                            return `${displayName !== null ? `${displayName}` : `无用户名或覆盖名（UID：${user.uid}）`}`;
                        } else {
                            return `${name} ${displayName !== null ? `（${displayName}）` : ''}`;
                        }
                    }
                }
            });

            const postRenderTableComponent = Vue.component('post-render-table', {
                template: '#post-render-table-template',
                props: {
                    posts: { type: Object, required: true }
                },
                data () {
                    return {
                        $$getTiebaUserLink,
                        $$getTiebaUserAvatarUrl,
                        $getUserInfo: window.$getUserInfo(this.$props.posts.users),
                        threads: [],
                        threadsReply: [],
                        repliesSubReply: [],
                        threadColumns: [
                            { title: 'tid', dataIndex: 'tid', scopedSlots: { customRender: 'tid' } },
                            { title: '标题', dataIndex: 'title', scopedSlots: { customRender: 'titleWithTag' } },
                            { title: '回复量', dataIndex: 'replyNum' },
                            { title: '浏览量', dataIndex: 'viewNum' },
                            { title: '发帖人', scopedSlots: { customRender: 'authorInfo' } },
                            { title: '发帖时间', dataIndex: 'postTime' },
                            { title: '最后回复人', scopedSlots: { customRender: 'latestReplierInfo' } },
                            { title: '最后回复时间', dataIndex: 'latestReplyTime' },
                            { title: '发帖人UID', dataIndex: 'authorUid' },
                            { title: '最后回复人UID', dataIndex: 'latestReplierUid' },
                            { title: '1楼pid', dataIndex: 'firstPid', scopedSlots: { customRender: 'firstPid' } },
                            { title: '主题贴类型', dataIndex: 'threadType' },// todo: unknown value enum struct
                            { title: '分享量', dataIndex: 'shareNum' },
                            { title: '赞踩量', dataIndex: 'agreeInfo' },// todo: unknown json struct
                            { title: '旧版客户端赞', dataIndex: 'zanInfo' },// todo: unknown json struct
                            { title: '发帖位置', dataIndex: 'location' },// todo: unknown json struct
                            { title: '首次收录时间', dataIndex: 'created_at' },
                            { title: '最后更新时间', dataIndex: 'updated_at' }
                        ],
                        replyColumns: [
                            { title: 'pid', dataIndex: 'pid' },
                            { title: '楼层', dataIndex: 'floor' },
                            { title: '楼中楼回复量', dataIndex: 'subReplyNum' },
                            { title: '发帖人', scopedSlots: { customRender: 'authorInfo' } },
                            { title: '发帖人UID', dataIndex: 'authorUid' },
                            { title: '发帖时间', dataIndex: 'postTime' },
                            { title: '是否折叠', dataIndex: 'isFold' },// todo: unknown value enum struct
                            { title: '赞踩量', dataIndex: 'agreeInfo' },// todo: unknown json struct
                            { title: '客户端小尾巴', dataIndex: 'signInfo' },// todo: unknown json struct
                            { title: '发帖来源', dataIndex: 'tailInfo' },// todo: unknown json struct
                            { title: '发帖位置', dataIndex: 'location' },// todo: unknown json struct
                            { title: '首次收录时间', dataIndex: 'created_at' },
                            { title: '最后更新时间', dataIndex: 'updated_at' }
                        ],
                        subReplyColumns: [
                            { title: 'spid', dataIndex: 'spid' },
                            { title: '发帖人', scopedSlots: { customRender: 'authorInfo' } },
                            { title: '发帖人UID', dataIndex: 'authorUid' },
                            { title: '发帖时间', dataIndex: 'postTime' },
                            { title: '首次收录时间', dataIndex: 'created_at' },
                            { title: '最后更新时间', dataIndex: 'updated_at' }
                        ]
                    };
                },
                mounted () {
                    this.$data.threads = this.$props.posts.threads;
                    this.$data.threadsReply = _.chain(this.$data.threads)
                        .map('replies')
                        .reject(_.isEmpty) // remove threads which haven't reply
                        .mapKeys((replies) => replies[0].tid) // convert threads' reply array to object for adding tid key
                        .value();
                    this.$data.repliesSubReply = _.chain(this.$data.threadsReply)
                        .toArray() // tid keyed object to array
                        .flatten() // flatten every thread's replies
                        .map('subReplies')
                        .reject(_.isEmpty) // remove replies which haven't sub reply
                        .mapKeys((subReplies) => subReplies[0].pid) // convert replies' sub reply array to object for adding pid key
                        .value();
                },
                methods: {
                    renderUsername (uid) {
                        let user = this.$data.$getUserInfo(uid);
                        let name = user.name;
                        let displayName = user.displayName;
                        if (name === null) {
                            return `${displayName !== null ? `${displayName}` : `无用户名或覆盖名（UID：${user.uid}）`}`;
                        } else {
                            return `${name} ${displayName !== null ? `（${displayName}）` : ''}`;
                        }
                    }
                }
            });

            const postRenderRawComponent = Vue.component('post-render-raw', {
                template: '#post-render-raw-template',
                props: {
                    posts: { type: Object, required: true }
                }
            });

            const loadingListPlaceholderComponent = Vue.component('loading-list-placeholder', {
                template: '#loading-list-placeholder-template'
            });

            const errorPlaceholderComponent = Vue.component('error-placeholder', {
                template: '#error-placeholder-template',
                props: {
                    error: { type: Object, required: true }
                }
            });

            const inputTextMatchParamComponent = Vue.component('input-text-match-param', {
                template: '#input-text-match-param-template',
                model: {
                    prop: 'param'
                },
                props: {
                    param: { type: Object, required: true },
                    paramIndex: { type: Number, required: true },
                    classes: { type: Object, required: true }
                },
                methods: {
                    modelEvent (name, value) {
                        this.$props.param.subParam[name] = value;
                        this.$emit('input', this.$props.param);
                    }
                }
            });

            const inputNumericParamComponent = Vue.component('input-numeric-param', {
                template: '#input-numeric-param-template',
                model: {
                    prop: 'param'
                },
                props: {
                    param: { type: Object, required: true },
                    classes: { type: Object, required: true },
                    placeholders: { type: Object, required: true }
                },
                methods: {
                    modelEvent (value) {
                        this.$props.param.value = value;
                        this.$emit('input', this.$props.param);
                    }
                }
            });

            const selectRangeComponent = Vue.component('select-range', {
                template: '#select-range-template',
                props: {
                    value: { type: String, required: true }
                }
            });

            const selectParamComponent = Vue.component('select-param', {
                template: '#select-param-template',
                props: {
                    currentParam: String
                },
                data () {
                    return {
                        paramsGroup: {
                            贴子ID: {
                                tid: 'tid（主题贴ID）',
                                pid: 'pid（回复贴ID）',
                                spid: 'spid（楼中楼ID）'
                            },
                            所有贴子类型: {
                                postTime: '发帖时间',
                                authorUid: '发帖人UID',
                                authorName: '发帖人用户名',
                                authorDisplayName: '发帖人覆盖名',
                                authorGender: '发帖人性别',
                                authorManagerType: '发帖人吧务级别'
                            },
                            仅主题贴: {
                                latestReplyTime: '最后回复时间',
                                threadTitle: '主题贴标题',
                                threadViewNum: '主题贴浏览量',
                                threadShareNum: '主题贴分享量',
                                threadReplyNum: '主题贴回复量',
                                threadProperties: '主题贴属性',
                                latestReplierUid: '最后回复人UID',
                                latestReplierName: '最后回复人用户名',
                                latestReplierDisplayName: '最后回复人覆盖名',
                                latestReplierGender: '最后回复人性别'
                            },
                            仅回复贴: {
                                replySubReplyNum: '楼中楼回复量'
                            },
                            仅回复贴或楼中楼: {
                                postContent: '贴子内容',
                                authorExpGrade: '发帖人经验等级'
                            }
                        }
                    };
                }
            });

            const baseQueryFormMixin = {
                template: '#query-form-template',
                data () {
                    return {
                        antd,
                        isRequesting: false,
                        uniqueParams: {},
                        params: [], // [{ name: '', value: '', subParam: { name: value } },...]
                        invalidParamsIndex: [],
                        paramsDefaultValue: {},
                        paramsPreprocessor: {},
                        paramsWatcher: {},
                        paramWatcher (newParamsArray, oldParamsArray) {
                            _.chain(newParamsArray)
                                .filter((param) => _.includes(_.keys(this.$data.paramsWatcher), param.name))
                                .each((param) => this.$data.paramsWatcher[param.name](param))
                                .value();
                        }
                    };
                },
                watch: {
                    uniqueParams: { handler: 'paramWatcher', deep: true },
                    params: { handler: 'paramWatcher', deep: true },
                    $route (to, from) {
                        if (to.path === from.path) {
                            return; // ignore when only hash has changed
                        }
                        if (! this.$data.isRequesting) { // isRequesting will be false when route change is not emit by <query-form>.submit()
                            // these query logic is for route changes which is not trigger by <query-form>.submit(), such as user emitted history.back() or go()
                            let isOnlyPageChanged = _.isEqual(_.omit(to.params, 'page'), _.omit(from.params, 'page'));
                            this.parseRoute(to);
                            if (isOnlyPageChanged || this.checkParams()) { // skip checkParams() when there's only page changed
                                this.$data.isRequesting = true;
                                this.$emit('query', { queryParams: this.flattenParams(), shouldReplacePage: ! isOnlyPageChanged });
                            }
                        }
                    }
                },
                beforeMount () {
                    this.$data.uniqueParams = _.mapValues(this.$data.uniqueParams, _.unary(this.fillParamWithDefaultValue));
                    this.$data.params = _.map(this.$data.params, _.unary(this.fillParamWithDefaultValue));
                },
                mounted () {
                    this.parseRoute(this.$route); // first time parse
                    if (this.checkParams()) { // query manually since route update event can't be triggered while first load
                        this.$data.isRequesting = true;
                        this.$emit('query', { queryParams: this.flattenParams(), shouldReplacePage: true });
                    }
                },
                methods: {
                    submit () {
                        if (this.checkParams()) { // check here to stop route submit
                            this.$data.isRequesting = true;
                            this.submitRoute();
                            this.$emit('query', { queryParams: this.flattenParams(), shouldReplacePage: true }); // force emit event to refresh new query since route update event won't emit when isRequesting is true
                        }
                    },
                    parseRoute (route) { throw('component must implements mixin abstract method'); },
                    checkParams () { throw('component must implements mixin abstract method'); },
                    submitRoute () { throw('component must implements mixin abstract method'); },
                    paramRowLastDomClass (paramIndex, params) {
                        return params.length === 1 ? {} : { // if it's the only row, class remains unchanged
                            'param-control-first-row': paramIndex === 0,
                            'param-control-middle-row': ! (paramIndex === 0 || paramIndex === params.length - 1),
                            'param-control-last-row': paramIndex === params.length - 1
                        };
                    },
                    escapeParamValue (value, unescape = false) {
                        if (_.isString(value)) {
                            _.map({ // we don't escape ',' since array type params is already known
                                '/': '%2F',
                                ';': '%3B'
                            }, (encode, char) => value = value.replace(unescape ? encode : char, unescape ? char : encode));
                        }
                        return value;
                    },
                    addParam (event) {
                        this.$data.params.push(this.fillParamWithDefaultValue({ name: event.target.value }));
                        event.target.value = 'add'; // reset to add option
                    },
                    changeParam (beforeParamIndex, afterParamName) {
                        _.pull(this.$data.invalidParamsIndex, beforeParamIndex);
                        this.$set(this.$data.params, beforeParamIndex, this.fillParamWithDefaultValue({ name: afterParamName }));
                    },
                    deleteParam (paramIndex) {
                        _.pull(this.$data.invalidParamsIndex, paramIndex);
                        this.$data.invalidParamsIndex = _.map(this.$data.invalidParamsIndex, (invalidParamIndex) => invalidParamIndex > paramIndex ? invalidParamIndex - 1 : invalidParamIndex); // move forward params index which is after current one
                        this.$delete(this.$data.params, paramIndex);
                    },
                    fillParamWithDefaultValue (param, resetToDefault = false) {
                        if (resetToDefault) { // cloneDeep to prevent defaultsDeep mutates origin object
                            return _.defaultsDeep(_.cloneDeep(this.$data.paramsDefaultValue[param.name]), param);
                        } else {
                            return _.defaultsDeep(_.cloneDeep(param), this.$data.paramsDefaultValue[param.name]);
                        }
                    },
                    clearParamDefaultValue (param) {
                        param = _.cloneDeep(param); // prevent changing origin param
                        let defaultParam = this.$data.paramsDefaultValue[param.name];
                        if (defaultParam === undefined) {
                            if (_.isEmpty(param.subParam)) {
                                delete param.subParam;
                            }
                            return param;
                        }
                        if (! (_.isNumber(param.value) || ! _.isEmpty(param.value)) // number is consider as empty in isEmpty(), to prevent this here we use complex boolean expression
                            || (_.isArray(param.value)
                                ? _.isEqual(_.sortBy(param.value), _.sortBy(defaultParam.value)) // sort array type param value for comparing
                                : param.value === defaultParam.value)) {
                            delete param.value;
                        }
                        _.each(defaultParam.subParam, (value, name) => {
                            if (param.subParam[name] === value || value === undefined) { // undefined means this sub param must be deleted, as part of the parent param value
                                delete param.subParam[name];
                            }
                        });
                        if (_.isEmpty(param.subParam)) {
                            delete param.subParam;
                        }
                        return _.isEqual(_.keys(param), ['name']) ? null : param;  // return null for further filter()
                    },
                    clearedParamsDefaultValue () {
                        return _.filter(_.map(this.$data.params, this.clearParamDefaultValue)); // filter() will remove falsy values like null
                    },
                    clearedUniqueParamsDefaultValue (...omitParams) {
                        return _.pickBy(_.mapValues(_.omit(this.$data.uniqueParams, omitParams), this.clearParamDefaultValue)); // mapValues() return object which remains keys, pickBy() like filter() for objects
                    },
                    parseParamRoute (routePath) {
                        _.chain(routePath)
                            .trim('/')
                            .split('/')
                            .filter() // filter() will remove falsy values like ''
                            .map((paramWithSub) => {
                                let parsedParam = { subParam: {} };
                                _.each(paramWithSub.split(';'), (params, paramIndex) => { // split multiple params
                                    let paramPair = [params.substr(0, params.indexOf(':')), this.escapeParamValue(params.substr(params.indexOf(':') + 1), true)]; // split kv pair by first colon, using substr to prevent split array type param value
                                    if (paramIndex === 0) { // main param
                                        [parsedParam.name, parsedParam.value] = paramPair;
                                    } else { // sub params
                                        parsedParam.subParam[paramPair[0]] = paramPair[1];
                                    }
                                });
                                return parsedParam;
                            })
                            .map(_.unary(this.fillParamWithDefaultValue))
                            .each((param) => {
                                if (_.includes(_.keys(this.$data.paramsPreprocessor), param.name)) {
                                    this.$data.paramsPreprocessor[param.name](param);
                                }
                                if (_.includes(_.keys(this.$data.uniqueParams), param.name)) { // is unique param
                                    this.$data.uniqueParams[param.name] = param;
                                } else {
                                    this.$data.params.push(param);
                                }
                            })
                            .value();
                    },
                    submitParamRoute (filteredUniqueParams, filteredParams) {
                        this.$router.push({ path: `/${_.chain([..._.values(filteredUniqueParams), ...filteredParams])
                            .map((param) => `${param.name}:${this.escapeParamValue(_.isArray(param.value) ? param.value.join(',') : param.value)}${_.map(param.subParam, (value, name) => `;${name}:${this.escapeParamValue(value)}`).join('')}`)  // format param to route path, e.g. name:value;subParamName:subParamValue...
                            .join('/')
                            .value()}` });
                    },
                    flattenParams () {
                        const flattenParam = (param) => {
                            let flatted = {};
                            flatted[param.name] = param.value;
                            return { ...flatted, ...param.subParam };
                        };
                        return [
                            ..._.map(_.values(this.clearedUniqueParamsDefaultValue()), flattenParam),
                            ..._.map(this.clearedParamsDefaultValue(), flattenParam)
                        ];
                    }
                }
            };

            const queryFormComponent = Vue.component('query-form', {
                mixins: [baseQueryFormMixin],
                props: {
                    forumList: { type: Array, required: true }
                },
                data () {
                    return {
                        uniqueParams: {
                            fid: { name: 'fid' },
                            postTypes: { name: 'postTypes' },
                            orderBy: { name: 'orderBy' }
                        },
                        paramsDefaultValue: {
                            numericParams: { subParam: { range: '=' } },
                            textMatchParams: { subParam: { matchBy: 'implicit', spaceSplit: false } },
                            fid: { value: 'NULL' },
                            postTypes: { value: ['thread', 'reply', 'subReply'] },
                            orderBy: { value: 'default', subParam: { direction: 'default' } },
                            get tid () { return this.numericParams; },
                            get pid () { return this.numericParams; },
                            get spid () { return this.numericParams; },
                            postTime: { subParam: { range: undefined } },
                            latestReplyTime: { subParam: { range: undefined } },
                            get threadTitle () { return this.textMatchParams; },
                            get postContent () { return this.textMatchParams; },
                            get threadViewNum () { return this.numericParams; },
                            get threadShareNum () { return this.numericParams; },
                            get threadReplyNum () { return this.numericParams; },
                            get replySubReplyNum () { return this.numericParams; },
                            threadProperties: { value: [] },
                            get authorUid () { return this.numericParams; },
                            get authorName () { return this.textMatchParams; },
                            get authorDisplayName () { return this.textMatchParams; },
                            get authorExpGrade () { return this.numericParams; },
                            get latestReplierUid () { return this.numericParams; },
                            get latestReplierName () { return this.textMatchParams; },
                            get latestReplierDisplayName () { return this.textMatchParams; }
                        },
                        paramsRequiredPostTypes: {
                            pid: [['reply', 'subReply'], 'OR'],
                            spid: [['subReply'], 'AND'],
                            latestReplyTime: [['thread'], 'AND'],
                            threadTitle: [['thread'], 'AND'],
                            postContent: [['reply', 'subReply'], 'OR'],
                            threadViewNum: [['thread'], 'AND'],
                            threadShareNum: [['thread'], 'AND'],
                            threadReplyNum: [['thread'], 'AND'],
                            replySubReplyNum: [['reply'], 'AND'],
                            threadProperties: [['thread'], 'AND'],
                            authorExpGrade: [['reply', 'subReply'], 'OR'],
                            latestReplierUid: [['thread'], 'AND'],
                            latestReplierName: [['thread'], 'AND'],
                            latestReplierDisplayName: [['thread'], 'AND'],
                            latestReplierGender: [['thread'], 'AND']
                        },
                        orderByRequiredPostTypes :{
                            tid: [['thread', 'reply', 'subReply'], 'OR'],
                            pid: [['reply', 'subReply'], 'OR'],
                            spid: [['subReply'], 'OR']
                        },
                        isOrderByInvalid: false,
                        isFidInvalid: false,
                        paramsPreprocessor: { // param is byref object so changes will sync
                            dateTimeRangeParams (param) {
                                param.subParam.range = param.value.split(',');
                            },
                            arrayTypeParams (param) {
                                param.value = param.value.split(',');
                            },
                            textMatchParams (param) {
                                param.subParam.spaceSplit = param.subParam.spaceSplit === 'true'; // literal string to bool convert
                            },
                            get postTypes () { return this.arrayTypeParams; },
                            get postTime () { return this.dateTimeRangeParams; },
                            get latestReplyTime () { return this.dateTimeRangeParams; },
                            get threadProperties () { return this.arrayTypeParams; },
                            get threadTitle () { return this.textMatchParams; },
                            get postContent () { return this.textMatchParams; },
                            get authorName () { return this.textMatchParams; },
                            get authorDisplayName () { return this.textMatchParams; },
                            get latestReplierName () { return this.textMatchParams; },
                            get latestReplierDisplayName () { return this.textMatchParams; }
                        },
                        paramsWatcher: { // param is byref object so changes will sync
                            dateTimeRangeParams (param) {
                                param.value = (param.subParam.range || []).join(','); // combine datetime range into root param's value
                            },
                            textMatchParams (param) {
                                if (param.subParam.matchBy === 'regex') {
                                    param.subParam.spaceSplit = false;
                                }
                            },
                            get postTime () { return this.dateTimeRangeParams; },
                            get latestReplyTime ()  { return this.dateTimeRangeParams; },
                            get threadTitle () { return this.textMatchParams; },
                            get postContent () { return this.textMatchParams; },
                            get authorName () { return this.textMatchParams; },
                            get authorDisplayName () { return this.textMatchParams; },
                            get latestReplierName () { return this.textMatchParams; },
                            get latestReplierDisplayName () { return this.textMatchParams; },
                            orderBy (param) {
                                if (param.value === 'default') { // reset to default
                                    param.subParam.direction = 'default';
                                }
                            }
                        }
                    };
                },
                methods: {
                    currentQueryType () {
                        let clearedParams = this.clearedParamsDefaultValue();
                        if (_.isEmpty(clearedParams)) { // is there no other params
                            if (_.isEmpty(this.clearedUniqueParamsDefaultValue('postTypes'))) {
                                return 'empty';
                            } else if (_.isEmpty(this.clearedUniqueParamsDefaultValue('fid', 'postTypes'))) { // note when query with postTypes param, the route will goto params not the fid
                                return 'fid';
                            }
                        }
                        if (_.isEmpty(_.reject(clearedParams, (param) => _.includes(['tid', 'pid', 'spid'], param.name))) // is there no other params
                            && _.filter(clearedParams, (param) => _.includes(['tid', 'pid', 'spid'], param.name)).length === 1) { // is there only one post id param
                            return 'postID';
                        }
                        return 'search';
                    },
                    parseRoute (route) {
                        this.$data.uniqueParams = _.mapValues(this.$data.uniqueParams, _.unary(this.fillParamWithDefaultValue));
                        this.$data.params = [];
                        // parse route path to params
                        if (route.name.startsWith('param')) {
                            this.parseParamRoute(route.params.pathMatch); // omit page param from route full path
                        } else if (route.name.startsWith('fid')) {
                            this.$data.uniqueParams.fid.value = route.params.fid;
                        } else { // post id routes
                            this.$data.uniqueParams = _.mapValues(this.$data.uniqueParams, (param) => this.fillParamWithDefaultValue(param, true)); // reset to default
                            this.$data.params = _.map(_.omit(route.params, 'pathMatch', 'page'), (value, name) => this.fillParamWithDefaultValue({ name, value }) );
                        }
                    },
                    checkParams () {
                        // check query type
                        this.$data.isFidInvalid = false;
                        let clearedUniqueParams = this.clearedUniqueParamsDefaultValue();
                        switch (this.currentQueryType()) {
                            case 'empty':
                                new Noty({ timeout: 3000, type: 'warning', text: '请选择贴吧或/并输入查询参数，勿只选择贴子类型参数'}).show();
                                return false; // exit early
                            case 'postID':
                                if (clearedUniqueParams.fid !== undefined) {
                                    this.$data.uniqueParams.fid.value = this.$data.paramsDefaultValue.fid.value; // reset fid to default value
                                    new Noty({ timeout: 3000, type: 'info', text: '已移除按贴索引查询所不需要的查询贴吧参数'}).show();
                                    this.submitRoute(); // update route to match new params without fid
                                }
                                return true; // index query doesn't restrict on post types
                            case 'search':
                                if (clearedUniqueParams.fid === undefined) {
                                    this.$data.isFidInvalid = true; // search query require fid param
                                    new Noty({ timeout: 3000, type: 'warning', text: '搜索查询必须指定查询贴吧'}).show();
                                }
                                break;
                        }
                        // check params required post types
                        let postTypes = _.sortBy(this.$data.uniqueParams.postTypes.value);
                        this.$data.invalidParamsIndex = []; // reset to prevent duplicate indexes
                        _.each(_.map(this.$data.params, this.clearParamDefaultValue), (param, paramIndex) => { // we don't filter() here for post types validate
                            if (param !== null && param.value !== undefined) { // is param have no diff with default value and have value
                                let paramRequiredPostTypes = this.$data.paramsRequiredPostTypes[param.name];
                                if (paramRequiredPostTypes !== undefined) { // not set means this param accepts any post types
                                    if (! (paramRequiredPostTypes[1] === 'OR' // does uniqueParams.postTypes fits with params required post types
                                        ? _.isEmpty(_.difference(postTypes, _.sortBy(paramRequiredPostTypes[0])))
                                        : _.isEqual(_.sortBy(paramRequiredPostTypes[0]), postTypes))) {
                                        this.$data.invalidParamsIndex.push(paramIndex);
                                    }
                                }
                            } else {
                                this.$data.invalidParamsIndex.push(paramIndex);
                            }
                        });
                        if (! _.isEmpty(this.$data.invalidParamsIndex)) {
                            new Noty({ timeout: 3000, type: 'warning', text: `第${_.map(this.$data.invalidParamsIndex, (i) => i + 1).join(',')}项查询参数与查询贴子类型要求不匹配`}).show();
                        }
                        // check order by required post types
                        this.$data.isOrderByInvalid = false;
                        let orderBy = this.$data.uniqueParams.orderBy.value;
                        if (_.includes(_.keys(this.$data.orderByRequiredPostTypes), orderBy)) {
                            let orderByRequiredPostTypes = this.$data.orderByRequiredPostTypes[orderBy];
                            if (! (orderByRequiredPostTypes[1] === 'OR'
                                ? _.isEmpty(_.difference(postTypes, _.sortBy(orderByRequiredPostTypes[0])))
                                : _.isEqual(_.sortBy(orderByRequiredPostTypes[0]), postTypes))) {
                                this.$data.isOrderByInvalid = true;
                                new Noty({ timeout: 3000, type: 'warning', text: '排序方式与查询贴子类型要求不匹配'}).show();
                            }
                        }

                        return _.isEmpty(this.$data.invalidParamsIndex) && ! this.$data.isOrderByInvalid && ! this.$data.isFidInvalid; // return false when there's any invalid params
                    },
                    submitRoute () {
                        // decide which route to go
                        let clearedParams = this.clearedParamsDefaultValue();
                        let clearedUniqueParams = this.clearedUniqueParamsDefaultValue();
                        if (_.isEmpty(clearedUniqueParams)) { // might be post id route
                            for (const postIDName of ['spid', 'pid', 'tid']) { // todo: sub posts id goes first to simply verbose multi post id condition
                                let postIDParam = _.filter(clearedParams, (param) => param.name === postIDName);
                                let isOnlyOnePostIDParam = _.isEmpty(_.reject(clearedParams, (param) => param.name === postIDName)) // is there no other params
                                    && postIDParam.length === 1 // is there only one post id param
                                    && postIDParam[0].subParam === undefined; // is range subParam not set
                                if (isOnlyOnePostIDParam) {
                                    this.$router.push({ name: postIDName, params: { [postIDName]: postIDParam[0].value } });
                                    return; // exit early to prevent pushing other route
                                }
                            }
                        }
                        if (clearedUniqueParams.fid !== undefined && _.isEmpty(clearedParams) && _.isEmpty(_.omit(clearedUniqueParams, 'fid'))) { // fid route
                            this.$router.push({ name: 'fid', params: { fid: clearedUniqueParams.fid.value } });
                            return;
                        }
                        this.submitParamRoute(clearedUniqueParams, clearedParams); // param route
                    }
                }
            });

            const postsQueryComponent = Vue.component('posts-query', {
                template: '#posts-query-template',
                data () {
                    return {
                        forumList: [],
                        postPages: [],
                        currentRoutesPage: parseInt(this.$route.params.page) || 1,
                        queryError: null,
                        renderType: 'list',
                        postsNavExpanded: false
                    };
                },
                computed: {
                    selectingRenderType: {
                        get: function () {
                            return [this.$data.renderType];
                        },
                        set: function (value) {
                            this.$data.renderType = value[0];
                        }
                    }
                },
                watch: {
                    $route (to, from) {
                        this.$data.currentRoutesPage = parseInt(to.params.page) || 1;
                    }
                },
                beforeMount () {
                    $$loadForumList().then((forumList) => this.$data.forumList = forumList);
                },
                methods: {
                    changeTitle (page = this.$data.currentRoutesPage) {
                        let forumName = `${this.$data.postPages[0].forum.name}吧`;
                        let threadTitle = this.$data.postPages[0].threads[0].title;
                        switch (this.$refs.queryForm.currentQueryType()) {
                            case 'fid':
                            case 'search':
                                document.title = `第${page}页 - ${forumName} - 贴子查询 - 贴吧云监控`;
                                break;
                            case 'postID':
                                document.title = `第${page}页 - 【${forumName}】${threadTitle} - 贴子查询 - 贴吧云监控`;
                                break;
                        }
                    },
                    loadPage (pageNum) {
                        this.$router.push(this.$route.name.startsWith('param')
                            ? { path: `/page/${pageNum}/${this.$route.params.pathMatch}` }
                            : {
                                name: `${this.$route.name}${this.$route.name.endsWith('+p') ? '' : '+p'}`,
                                params: { ...this.$route.params, page: pageNum }
                            });
                    },
                    query ({ queryParams, shouldReplacePage }) {
                        this.$data.queryError = null;
                        if (shouldReplacePage) {
                            this.$data.postPages = []; // clear posts pages data before request to show loading placeholder
                        } else {
                            if (! _.isEmpty(_.filter(_.map(this.$data.postPages, 'pages.currentPage'), (i) => i === this.$data.currentRoutesPage))) {
                                this.$refs.queryForm.$data.isRequesting = false;
                                return; // cancel request when requesting page have already been loaded
                            }
                        }

                        let ajaxStartTime = Date.now();
                        if (window.$previousPostsQueryAjax !== undefined) { // cancel previous pending ajax to prevent conflict
                            window.$previousPostsQueryAjax.abort();
                        }
                        $$reCAPTCHACheck().then((reCAPTCHA) => {
                            window.$previousPostsQueryAjax = $.getJSON(`${$$baseUrl}/api/postsQuery`, $.param({ query: JSON.stringify(queryParams), page: this.$data.currentRoutesPage, reCAPTCHA}));
                            window.$previousPostsQueryAjax
                                .done((ajaxData) => {
                                    if (shouldReplacePage) { // is loading next page data on the same query params or requesting new query with different params
                                        this.$data.postPages = [ajaxData];
                                    } else {
                                        // insert after existing previous page, if not exist will be inserted at start
                                        this.$data.postPages.splice(_.findIndex(this.$data.postPages, { pages: { currentPage: this.$data.currentRoutesPage - 1 } }) + 1, 0, ajaxData);
                                    }
                                    new Noty({ timeout: 3000, type: 'success', text: `已加载第${ajaxData.pages.currentPage}页 ${ajaxData.pages.currentItems}条贴子 耗时${Date.now() - ajaxStartTime}ms`}).show();
                                    //this.changeDocumentTitle(this.$route);
                                })
                                .fail((jqXHR) => {
                                    this.$data.postPages = [];
                                    if (jqXHR.responseJSON !== undefined) {
                                        let error = jqXHR.responseJSON;
                                        if (_.isObject(error.errorInfo)) { // response when laravel failed validate, same with ajaxError jquery event @ layout.blade.php
                                            this.$data.queryError = { code: error.errorCode, info: _.map(error.errorInfo, (info, paramName) => `参数 ${paramName}：${info.join('<br />')}`).join('<br />') };
                                        } else {
                                            this.$data.queryError = { code: error.errorCode, info: error.errorInfo };
                                        }
                                    }
                                })
                                .always(() => this.$refs.queryForm.$data.isRequesting = false);
                        });
                    }
                }
            });

            const postsQueryVue = new Vue({
                el: '#root-vue',
                router: new VueRouter({
                    mode: 'history',
                    base: `${$$baseUrlDir}/postMulti`,
                    routes: [
                        {
                            name: 'root',
                            path: '/',
                            component: postsQueryComponent,
                            children: [
                                { name: 'fid', path: 'fid/:fid' },
                                { name: 'fid+p', path: 'fid/:fid/page/:page' },
                                { name: 'tid', path: 'tid/:tid' },
                                { name: 'tid+p', path: 'tid/:tid/page/:page' },
                                { name: 'pid', path: 'pid/:pid' },
                                { name: 'pid+p', path: 'pid/:pid/page/:page' },
                                { name: 'spid', path: 'spid/:spid' },
                                { name: 'spid+p', path: 'spid/:spid/page/:page' },
                                { name: 'param+p', path: 'page/:page/*' },
                                { name: 'param', path: '*' },
                            ]
                        }
                    ]
                })
            });
        </script>
    @endverbatim
@endsection
