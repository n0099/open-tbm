using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac.Features.Indexed;
using LinqKit;
using Microsoft.Extensions.Logging;
using static System.Text.Json.JsonElement;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;
using Uid = System.Int64;
using Time = System.UInt32;

namespace tbm.Crawler
{
    public sealed class ReplyCrawler : BaseCrawler<ReplyPost>
    {
        private readonly Tid _tid;
        private ThreadLateSaveInfo? _parentThread;

        public delegate ReplyCrawler New(Fid fid, Tid tid);

        public ReplyCrawler(
            ILogger<ReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            UserParser userParser,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid, Tid tid
        ) : base(logger, requester, requesterTcs, userParser, (locks["reply"]("reply"), tid), fid) => _tid = tid;

        protected override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            return e;
        }

        protected override async Task<JsonElement> CrawlSinglePage(uint page) =>
            await RequestJson("http://c.tieba.baidu.com/c/f/pb/page", new Dictionary<string, string>
            { // reverse order will be {"last", "1"}, {"r", "1"}
                {"kz", _tid.ToString()},
                {"pn", page.ToString()}
            });

        protected override void ValidateJsonThenParse(JsonElement json)
        {
            var posts = GetValidPosts(json);
            ParsePosts(posts);

            var parentThread = json.GetProperty("thread").GetProperty("thread_info");
            _parentThread = new ThreadLateSaveInfo
            {
                AuthorPhoneType = parentThread.GetStrProp("phone_type"),
                AntiSpamInfo = RawJsonOrNullWhenEmpty(parentThread.GetProperty("antispam_info"))
            };

            var users = json.GetProperty("user_list").EnumerateArray();
            Users.ParseUsers(users);
            lock (Posts)
                posts.Select(p => Pid.Parse(p.GetStrProp("id"))).ForEach(pid =>
                {
                    var p = Posts[pid];
                    var author = users.First(u => Uid.Parse(u.GetStrProp("id")) == p.AuthorUid);
                    p.AuthorManagerType = author.TryGetProperty("bawu_type", out var bawuType)
                        ? bawuType.GetString().NullIfWhiteSpace()
                        : null; // will be null if he's not a moderator
                    p.AuthorExpGrade = author.TryGetProperty("level_id", out var levelIdEl)
                        ? ushort.TryParse(levelIdEl.GetString(), out var levelId) ? levelId : null
                        : null; // will be null when author is a historical anonymous user
                    Posts[pid] = p;
                });
        }

        protected override ArrayEnumerator GetValidPosts(JsonElement json)
        {
            var errorCode = json.GetProperty("error_code").GetString();
            if (errorCode == "4")
                throw new TiebaException("Thread already deleted when crawling reply");
            ValidateOtherErrorCode(json);
            return EnsureNonEmptyPostList(json, "post_list",
                "Reply list is empty, posts might already deleted from tieba");
        }

        protected override void ParsePosts(ArrayEnumerator posts)
        {
            var newPosts = posts.Select(p =>
            {
                var post = new ReplyPost();
                try
                {
                    post.Tid = _tid;
                    post.Pid = Pid.Parse(p.GetStrProp("id"));
                    post.Floor = uint.Parse(p.GetStrProp("floor"));
                    post.Content = RawJsonOrNullWhenEmpty(p.GetProperty("content"));
                    post.AuthorUid = Uid.Parse(p.GetStrProp("author_id"));
                    post.SubReplyNum = uint.Parse(p.GetStrProp("sub_post_number"));
                    post.PostTime = Time.Parse(p.GetStrProp("time"));
                    post.IsFold = p.GetStrProp("is_fold") != "0";
                    post.AgreeNum = int.Parse(p.GetProperty("agree").GetStrProp("agree_num"));
                    post.DisagreeNum = int.Parse(p.GetProperty("agree").GetStrProp("disagree_num"));
                    post.Location = RawJsonOrNullWhenEmpty(p.GetProperty("lbs_info"));
                    post.SignInfo = RawJsonOrNullWhenEmpty(p.GetProperty("signature"));
                    post.TailInfo = RawJsonOrNullWhenEmpty(p.GetProperty("tail_info"));
                    return post;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = p.GetRawText();
                    throw new Exception("Reply parse error", e);
                }
            });
            lock (Posts) newPosts.ForEach(i => Posts[i.Pid] = i);
        }

        protected override void SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<ReplyPost>(p => Posts.Keys.Any(id => id == p.Pid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Pid)),
            p => p.Pid,
            i => i.Pid,
            p => new PostIndex {Type = "reply", Fid = Fid, Tid = p.Tid, Pid = p.Pid, PostTime = p.PostTime},
            (now, p) => new ReplyRevision {Time = now, Tid = p.Tid, Pid = p.Pid});
    }
}
