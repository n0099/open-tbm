using System.Collections.Generic;
using System.Linq;
using Autofac.Features.Indexed;
using Google.Protobuf;
using Microsoft.Extensions.Logging;
using TbClient.Api.Response;
using TbClient.Post;
using Fid = System.UInt32;
using Tid = System.UInt64;

namespace tbm.Crawler
{
    public class ReplyCrawlFacade : BaseCrawlFacade<ReplyPost, ReplyResponse, Reply, ReplyCrawler>
    {
        public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

        public ReplyCrawlFacade(ILogger<ReplyCrawlFacade> logger, ReplyCrawler.New crawler,
            ReplyParser parser, ReplySaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, Tid tid
        ) : base(logger, crawler(tid), parser, saver, users, requesterTcs, (locks["reply"]("reply"), fid), fid)
        {
        }

        protected override void ValidateThenParse((ReplyResponse, CrawlRequestFlag) responseAndFlag)
        {
            var response = responseAndFlag.Item1;
            var posts = Crawler.GetValidPosts(response);
            Parser.ParsePosts(responseAndFlag.Item2, Crawler.GetValidPosts(responseAndFlag.Item1), Posts, Users);

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
    }
}
