using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac.Features.Indexed;
using Google.Protobuf;
using LinqKit;
using Microsoft.Extensions.Logging;
using TbClient.Api.Request;
using TbClient.Api.Response;
using TbClient.Post;
using Fid = System.UInt32;
using Tid = System.UInt64;

namespace tbm.Crawler
{
    public sealed class ReplyCrawler : BaseCrawler<ReplyPost, ReplyResponse, Reply>
    {
        private readonly Tid _tid;
        private ThreadLateSaveInfo? _parentThread;

        public delegate ReplyCrawler New(Fid fid, Tid tid);

        public ReplyCrawler(
            ILogger<ReplyCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            UserParserAndSaver userParser,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid, Tid tid
        ) : base(logger, requester, requesterTcs, userParser, (locks["reply"]("reply"), tid), fid) => _tid = tid;

        protected override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            return e;
        }

        protected override async Task<ReplyResponse> CrawlSinglePage(uint page) =>
            await Requester.RequestProtoBuf<ReplyRequest, ReplyResponse>(
                "http://c.tieba.baidu.com/c/f/pb/page?cmd=302001",
                new ReplyRequest
                {
                    Data = new ReplyRequest.Types.Data
                    {
                        // reverse order will be {"last", "1"}, {"r", "1"}
                        Kz = (long)_tid,
                        Pn = (int)page,
                        Rn = 30,
                        QType = 2
                    }
                }
            );

        protected override void ValidateThenParse(ReplyResponse response)
        {
            var posts = GetValidPosts(response);
            ParsePosts(posts);

            var data = (IMessage)ReplyResponse.Descriptor.FindFieldByName("data").Accessor.GetValue(response);
            /*
            var parentThread = (Thread)data.Descriptor.FindFieldByName("thread").Accessor.GetValue(data);
            _parentThread = new ThreadLateSaveInfo
            {
                AuthorPhoneType = parentThread.GetStrProp("phone_type"),
                AntiSpamInfo = RawJsonOrNullWhenEmpty(parentThread.GetProperty("antispam_info"))
            };
            */
            var users = (List<TbClient.User>)data.Descriptor.FindFieldByName("userList").Accessor.GetValue(data);
            Users.ParseUsers(users);
            posts.Select(p => p.Pid).ForEach(pid =>
            { // fill the value of some fields of reply from user list which is out of post list
                var p = Posts[pid];
                var author = users.First(u => u.Id == p.AuthorUid);
                p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                p.AuthorExpGrade = (ushort)author.LevelId; // will be null when author is a historical anonymous user
                Posts[pid] = p;
            });
        }

        protected override IList<(Reply, CrawlRequestFlag)> GetValidPosts((ReplyResponse, CrawlRequestFlag) responseAndFlag)
        {
            var error = (TbClient.Error)ReplyResponse.Descriptor.FindFieldByName("error").Accessor.GetValue(responseAndFlag);
            if (error.Errorno == 4)
                throw new TiebaException("Thread already deleted when crawling reply");
            ValidateOtherErrorCode(responseAndFlag);
            return EnsureNonEmptyPostList(responseAndFlag, 6,
                "Reply list is empty, posts might already deleted from tieba");
        }

        protected override void ParsePosts(IEnumerable<Reply> posts)
        {
            var newPosts = posts.Select(el =>
            {
                var p = new ReplyPost();
                try
                {
                    p.Tid = _tid;
                    p.Pid = el.Pid;
                    p.Floor = el.Floor;
                    p.Content = RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Content));
                    p.AuthorUid = el.AuthorId;
                    p.SubReplyNum = (int)el.SubPostNumber;
                    p.PostTime = el.Time;
                    p.IsFold = (ushort)el.IsFold;
                    p.AgreeNum = (int)el.Agree.AgreeNum;
                    p.DisagreeNum = (int)el.Agree.DisagreeNum;
                    p.Location = RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.LbsInfo));
                    p.SignInfo = RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Signature));
                    p.TailInfo = RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.TailInfo));
                    return p;
                }
                catch (Exception e)
                {
                    e.Data["rawJson"] = JsonSerializer.Serialize(el);
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
            var parentThread = new ThreadPost
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
