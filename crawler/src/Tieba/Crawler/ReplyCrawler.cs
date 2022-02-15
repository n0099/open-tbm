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
            posts.Select(p => Pid.Parse(p.GetStrProp("id"))).ForEach(pid =>
            { // fill the value of some fields of reply from user list which is out of post list
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
            var errorCode = json.GetStrProp("error_code");
            if (errorCode == "4")
                throw new TiebaException("Thread already deleted when crawling reply");
            ValidateOtherErrorCode(json);
            return EnsureNonEmptyPostList(json, "post_list",
                "Reply list is empty, posts might already deleted from tieba");
        }

        protected override void ParsePosts(ArrayEnumerator posts)
        {
            var newPosts = posts.Select(el =>
            {
                var p = new ReplyPost();
                try
                {
                    p.Tid = _tid;
                    p.Pid = Pid.Parse(el.GetStrProp("id"));
                    p.Floor = uint.Parse(el.GetStrProp("floor"));
                    p.Content = RawJsonOrNullWhenEmpty(el.GetProperty("content"));
                    p.AuthorUid = Uid.Parse(el.GetStrProp("author_id"));
                    p.SubReplyNum = uint.Parse(el.GetStrProp("sub_post_number"));
                    p.PostTime = Time.Parse(el.GetStrProp("time"));
                    p.IsFold = el.GetStrProp("is_fold") != "0";
                    p.AgreeNum = int.Parse(el.GetProperty("agree").GetStrProp("agree_num"));
                    p.DisagreeNum = int.Parse(el.GetProperty("agree").GetStrProp("disagree_num"));
                    p.Location = RawJsonOrNullWhenEmpty(el.GetProperty("lbs_info"));
                    p.SignInfo = RawJsonOrNullWhenEmpty(el.GetProperty("signature"));
                    p.TailInfo = RawJsonOrNullWhenEmpty(el.GetProperty("tail_info"));
                    return p;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = el.GetRawText();
                    throw new Exception("Reply parse error", e);
                }
            });
            newPosts.ForEach(i => Posts[i.Pid] = i);
        }

        protected override void SavePosts(TbmDbContext db) {
            SavePosts(db,
                PredicateBuilder.New<ReplyPost>(p => Posts.Keys.Any(id => id == p.Pid)),
                PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Pid)),
                p => p.Pid,
                i => i.Pid,
                p => new PostIndex {Type = "reply", Fid = Fid, Tid = p.Tid, Pid = p.Pid, PostTime = p.PostTime},
                (now, p) => new ReplyRevision {Time = now, Pid = p.Pid});

            if (_parentThread == null) return;
            var parentThread = new ThreadPost()
            {
                Tid = _tid,
                AuthorPhoneType = _parentThread.AuthorPhoneType,
                AntiSpamInfo = _parentThread.AntiSpamInfo
            };
            db.Attach(parentThread);
            db.Entry(parentThread).Properties
                .Where(p => p.Metadata.Name is nameof(ThreadLateSaveInfo.AuthorPhoneType) or nameof(ThreadLateSaveInfo.AntiSpamInfo))
                .ForEach(p => p.IsModified = true);
        }
    }
}
