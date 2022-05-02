namespace tbm.Crawler
{
    public class ReplyCrawlFacade : BaseCrawlFacade<ReplyPost, ReplyResponse, Reply, ReplyCrawler>
    {
        private readonly Tid _tid;

        public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

        public ReplyCrawlFacade(ILogger<ReplyCrawlFacade> logger, ReplyCrawler.New crawler,
            ReplyParser parser, ReplySaver.New saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks.New> locks, Fid fid, Tid tid
        ) : base(logger, crawler(fid, tid), parser, saver.Invoke, users, requesterTcs, (locks["reply"]("reply"), tid), fid) => _tid = tid;

        protected override void PostParseCallback((ReplyResponse, CrawlRequestFlag) responseAndFlag, IList<Reply> posts)
        {
            var data = responseAndFlag.Item1.Data;
            if (data.Page.CurrentPage == 1)
            { // update parent thread of reply with new title that extracted from the first floor reply in first page
                using var scope = Program.Autofac.BeginLifetimeScope();
                var db = scope.Resolve<TbmDbContext.New>()(Fid);
                var parentThreadTitle = (from t in db.Threads where t.Tid == _tid select t.Title).FirstOrDefault();
                if (parentThreadTitle == "")
                { // thread title will be empty string as a fallback when the thread author haven't write title for this thread
                    var newTitle = posts.FirstOrDefault(p => p.Floor == 1)?.Title;
                    if (newTitle != null)
                    {
                        db.Attach(new ThreadPost {Tid = _tid, Title = newTitle}).Property(t => t.Title).IsModified = true;
                        if (db.SaveChangesWithoutTimestamping() != 1) // do not touch UpdateAt field for the accuracy of time field in thread revisions
                            throw new DbUpdateException($"parent thread title \"{newTitle}\" completion for tid {_tid} has failed");
                    }
                }
            }

            var users = data.UserList;
            Users.ParseUsers(users);

            posts.Select(p => p.Pid).ForEach(pid =>
            { // fill the values for some field of reply from user list which is out of post list
                var p = ParsedPosts[pid];
                var author = users.First(u => u.Uid == p.AuthorUid);
                p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                p.AuthorExpGrade = (ushort)author.LevelId; // will be null when author is a historical anonymous user

                p.Tid = _tid;
                ParsedPosts[pid] = p;
            });
        }
    }
}
