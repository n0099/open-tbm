namespace tbm.Crawler
{
    public class ReplyCrawlFacade : BaseCrawlFacade<ReplyPost, ReplyResponse, Reply, ReplyCrawler>
    {
        private readonly Tid _tid;

        public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

        public ReplyCrawlFacade(ILogger<ReplyCrawlFacade> logger, ReplyCrawler.New crawler,
            ReplyParser parser, ReplySaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, Tid tid
        ) : base(logger, crawler(tid), parser, saver.Invoke, users, requesterTcs, (locks["reply"]("reply"), fid), fid) => _tid = tid;

        protected override void PostParseCallback((ReplyResponse, CrawlRequestFlag) responseAndFlag, IEnumerable<Reply> posts)
        {
            var data = (IMessage)ReplyResponse.Descriptor.FindFieldByName("data").Accessor.GetValue(responseAndFlag.Item1);
            /*
            var parentThread = (Thread)data.Descriptor.FindFieldByName("thread").Accessor.GetValue(data);
            _parentThread = new ThreadLateSaveInfo
            {
                AuthorPhoneType = parentThread.GetStrProp("phone_type"),
                AntiSpamInfo = RawJsonOrNullWhenEmpty(parentThread.GetProperty("antispam_info"))
            };
            */
            var users = (IList<User>)data.Descriptor.FindFieldByNumber(ReplyResponse.Types.Data.UserListFieldNumber).Accessor.GetValue(data);
            Users.ParseUsers(users);
            posts.Select(p => p.Pid).ForEach(pid =>
            { // fill the value of some fields of reply from user list which is out of post list
                var p = Posts[pid];
                var author = users.First(u => u.Uid == p.AuthorUid);
                p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                p.AuthorExpGrade = (ushort)author.LevelId; // will be null when author is a historical anonymous user

                p.Tid = _tid;
                Posts[pid] = p;
            });
        }
    }
}
