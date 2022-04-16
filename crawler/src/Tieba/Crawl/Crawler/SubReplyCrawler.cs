using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac.Features.Indexed;
using LinqKit;
using Microsoft.Extensions.Logging;
using TbClient.Api.Request;
using TbClient.Api.Response;
using TbClient.Post;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;

namespace tbm.Crawler
{
    public sealed class SubReplyCrawler : BaseCrawler<SubReplyPost, SubReplyResponse, SubReply>
    {
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawler New(Fid fid, Tid tid, Pid pid);

        public SubReplyCrawler(
            ILogger<SubReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            UserParserAndSaver userParser,
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

        protected override async Task<SubReplyResponse> CrawlSinglePage(uint page) =>
            await Requester.RequestProtoBuf<SubReplyRequest, SubReplyResponse>(
                "http://c.tieba.baidu.com/c/f/pb/floor?cmd=302002",
                new SubReplyRequest
                {
                    Data = new SubReplyRequest.Types.Data
                    {
                        Kz = (long)_tid,
                        Pid = (long)_pid,
                        Pn = (int)page
                    }
                }
            );

        protected override IList<(SubReply, CrawlRequestFlag)> GetValidPosts((SubReplyResponse, CrawlRequestFlag) responseAndFlag)
        {
            var error = (TbClient.Error)SubReplyResponse.Descriptor.FindFieldByName("error").Accessor.GetValue(responseAndFlag);
            switch (error.Errorno)
            {
                case 4:
                    throw new TiebaException("Reply already deleted when crawling sub reply");
                case 28:
                    throw new TiebaException("Thread already deleted when crawling sub reply");
                default:
                    ValidateOtherErrorCode(responseAndFlag);
                    return EnsureNonEmptyPostList(responseAndFlag, 4,
                        "Sub reply list is empty, posts might already deleted from tieba");
            }
        }

        protected override void ParsePosts(IEnumerable<SubReply> posts)
        {
            List<TbClient.User> users = new();
            var newPosts = posts.Select(el =>
            {
                var author = el.Author;
                users.Add(author);
                var p = new SubReplyPost();
                try
                {
                    p.Tid = _tid;
                    p.Pid = _pid;
                    p.Spid = el.Spid;
                    p.Content = RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Content));
                    p.AuthorUid = author.Id;
                    p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                    p.AuthorExpGrade = (ushort)author.LevelId;
                    p.PostTime = el.Time;
                    return p;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = JsonSerializer.Serialize(el);
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
            (now, p) => new SubReplyRevision {Time = now, Spid = p.Spid});
    }
}
