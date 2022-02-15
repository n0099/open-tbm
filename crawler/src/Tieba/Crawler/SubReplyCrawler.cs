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
using Spid = System.UInt64;
using Uid = System.Int64;
using Time = System.UInt32;

namespace tbm.Crawler
{
    public sealed class SubReplyCrawler : BaseCrawler<SubReplyPost>
    {
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawler New(Fid fid, Tid tid, Pid pid);

        public SubReplyCrawler(
            ILogger<SubReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            UserParser userParser,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid, Tid tid, Pid pid
        ) : base(logger, requester, requesterTcs, userParser, (locks["subReply"]("subReply"), pid), fid)
        {
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
            var errorCode = json.GetStrProp("error_code");
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
                var post = new SubReplyPost();
                try
                {
                    post.Tid = _tid;
                    post.Pid = _pid;
                    post.Spid = Spid.Parse(p.GetStrProp("id"));
                    post.Content = RawJsonOrNullWhenEmpty(p.GetProperty("content"));
                    post.AuthorUid = Uid.Parse(author.GetStrProp("id"));
                    post.AuthorManagerType = author.TryGetProperty("bawu_type", out var bawuType)
                        ? bawuType.GetString().NullIfWhiteSpace()
                        : null; // will be null if he's not a moderator
                    post.AuthorExpGrade = ushort.Parse(author.GetStrProp("level_id"));
                    post.PostTime = Time.Parse(p.GetStrProp("time"));
                    return post;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = p.GetRawText();
                    throw new Exception("Sub reply parse error", e);
                }
            });
            newPosts.ForEach(i => Posts[i.Spid] = i);
            Users.ParseUsers(users);
        }

        protected override void SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<SubReplyPost>(p => Posts.Keys.Any(id => id == p.Spid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Spid)),
            p => p.Spid,
            i => i.Spid,
            p => new PostIndex {Type = "reply", Fid = Fid, Tid = p.Tid, Pid = p.Pid, Spid = p.Spid, PostTime = p.PostTime},
            (now, p) => new SubReplyRevision {Time = now, Tid = p.Tid, Pid = p.Pid, Spid = p.Spid});
    }
}
