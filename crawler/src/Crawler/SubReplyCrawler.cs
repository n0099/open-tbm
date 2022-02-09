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
using Spid = System.UInt64;
using Uid = System.Int64;
using Time = System.UInt32;

namespace tbm.Crawler
{
    public sealed class SubReplyCrawler : BaseCrawler<SubReplyPost>
    {
        protected override CrawlerLocks CrawlerLocks { get; init; }
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawler New(Fid fid, Tid tid, Pid pid);

        public SubReplyCrawler(
            ILogger<SubReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            UserParser userParser,
            Fid fid, Tid tid, Pid pid
        ) : base(logger, requester, requesterTcs, userParser, fid)
        {
            CrawlerLocks = locks["subReply"]("subReply");
            _tid = tid;
            _pid = pid;
        }

        protected override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            e.Data["pid"] = _pid;
            return e;
        }

        protected override async Task<JsonElement> CrawlSinglePage(uint page) =>
            await RequestJson("http://c.tieba.baidu.com/c/f/pb/floor", new Dictionary<string, string>
            {
                {"kz", _tid.ToString()},
                {"pid", _pid.ToString()},
                {"pn", page.ToString()}
            });

        protected override ArrayEnumerator GetValidPosts(JsonElement json)
        {
            var errorCode = json.GetProperty("error_code").GetString();
            switch (errorCode)
            {
                case "4":
                    throw new TiebaException("Reply already deleted when crawling sub reply");
                case "28":
                    throw new TiebaException("Thread already deleted when crawling sub reply");
                default:
                    ValidateOtherErrorCode(json);
                    return EnsureNonEmptyPostList(json, "subpost_list",
                        "Sub reply list is empty, posts might already deleted from tieba");
            }
        }

        protected override void ParsePosts(ArrayEnumerator posts)
        {
            List<JsonElement> users = new();
            var newPosts = posts.Select(p =>
            {
                var author = p.GetProperty("author");
                users.Add(author);
                return new SubReplyPost
                {
                    Tid = _tid,
                    Pid = _pid,
                    Spid = Spid.Parse(p.GetStrProp("id")),
                    Content = RawJsonOrNullWhenEmpty(p.GetProperty("content")),
                    AuthorUid = Uid.Parse(author.GetStrProp("id")),
                    AuthorManagerType = author.TryGetProperty("bawu_type", out var bawuType)
                        ? bawuType.GetString().NullIfWhiteSpace()
                        : null, // will be null if he's not a moderator
                    AuthorExpGrade = ushort.Parse(author.GetStrProp("level_id")),
                    PostTime = Time.Parse(p.GetStrProp("time"))
                };
            });
            newPosts.ToList().ForEach(i => Posts[i.Spid] = i);
            Users.ParseUsers(users);
        }

        public override void SavePosts() => throw new NotImplementedException();
    }
}
