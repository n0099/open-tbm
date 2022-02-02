using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac.Features.Indexed;
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
        protected override CrawlerLocks CrawlerLocks { get; init; }
        private readonly Tid _tid;
        private ThreadLateSaveInfo? _parentThread;

        public delegate ReplyCrawler New(Fid fid, Tid tid);

        public ReplyCrawler(
            ILogger<ReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            UserParser userParser,
            Fid fid, Tid tid
        ) : base(logger, requester, requesterTcs, userParser, fid)
        {
            CrawlerLocks = locks["reply"]("reply");
            _tid = tid;
        }

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
                AntiSpamInfo = parentThread.GetProperty("antispam_info").GetRawText()
            };

            var users = json.GetProperty("user_list").EnumerateArray();
            Users.ParseUsers(users);
            lock (Posts)
                posts.Select(p => Pid.Parse(p.GetStrProp("id"))).ToList().ForEach(pid =>
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
                throw new Exception("Thread already deleted when crawling reply");
            ValidateOtherErrorCode(json);
            return EnsureNonEmptyPostList(json, "post_list",
                "Reply list is empty, posts might already deleted from tieba");
        }

        protected override void ParsePosts(ArrayEnumerator posts)
        {
            var newPosts = posts.Select(p => new ReplyPost
            {
                Tid = _tid,
                Pid = Pid.Parse(p.GetStrProp("id")),
                Floor = uint.Parse(p.GetStrProp("floor")),
                Content = NullIfEmptyJsonLiteral(p.GetProperty("content").GetRawText()),
                AuthorUid = Uid.Parse(p.GetStrProp("author_id")),
                SubReplyNum = uint.Parse(p.GetStrProp("sub_post_number")),
                PostTime = Time.Parse(p.GetStrProp("time")),
                IsFold = p.GetStrProp("is_fold") != "0",
                AgreeNum = int.Parse(p.GetProperty("agree").GetStrProp("agree_num")),
                DisagreeNum = int.Parse(p.GetProperty("agree").GetStrProp("disagree_num")),
                Location = NullIfEmptyJsonLiteral(p.GetProperty("lbs_info").GetRawText()),
                SignInfo = NullIfEmptyJsonLiteral(p.GetProperty("signature").GetRawText()),
                TailInfo = NullIfEmptyJsonLiteral(p.GetProperty("tail_info").GetRawText())
            });
            newPosts.ToList().ForEach(i => { Posts[i.Pid] = i; });
        }
    }
}
