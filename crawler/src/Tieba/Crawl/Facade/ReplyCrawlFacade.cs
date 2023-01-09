namespace tbm.Crawler.Tieba.Crawl.Facade
{
    public class ReplyCrawlFacade : BaseCrawlFacade<ReplyPost, ReplyResponse, Reply, ReplyCrawler>
    {
        private readonly TbmDbContext.New _dbContextFactory;
        private readonly SonicPusher _pusher;
        private readonly Tid _tid;

        public delegate ReplyCrawlFacade New(Fid fid, Tid tid);

        public ReplyCrawlFacade(ILogger<ReplyCrawlFacade> logger,
            TbmDbContext.New parentDbContextFactory, TbmDbContext.New dbContextFactory,
            ReplyCrawler.New crawler, ReplyParser parser, ReplySaver.New saver, UserParserAndSaver users, SonicPusher pusher,
            ClientRequesterTcs requesterTcs, IIndex<string, CrawlerLocks> locks, Fid fid, Tid tid
        ) : base(logger, parentDbContextFactory, crawler(fid, tid), parser, saver.Invoke, users, requesterTcs, (locks["reply"], new (fid, tid)), fid)
        {
            _dbContextFactory = dbContextFactory;
            _pusher = pusher;
            _tid = tid;
        }

        protected override void ThrowIfEmptyUsersEmbedInPosts() =>
            throw new TiebaException(
                $"User list in the response of reply request for fid {Fid}, tid {_tid} is empty.");

        protected override void ParsePostsEmbeddedUsers(IEnumerable<User> usersEmbedInPosts, IEnumerable<Reply> postsInCurrentResponse) =>
            ParsedPosts.Values // only mutate posts which occurs in current response
                .IntersectBy(postsInCurrentResponse.Select(r => r.Pid), r => r.Pid)
                .Join(usersEmbedInPosts, r => r.AuthorUid, u => u.Uid, (r, a) => (r, a))
                .ForEach(tuple =>
                { // fill the values for some field of reply from user list which is out of post list
                    var (r, author) = tuple;
                    r.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                    r.AuthorExpGrade = (ushort)author.LevelId; // will be null when author is a historical anonymous user
                });

        protected override void PostParseHook(ReplyResponse response, CrawlRequestFlag flag)
        {
            ParsedPosts.Values.ForEach(r => r.Tid = _tid);

            var data = response.Data;
            // update parent thread of reply with new title that extracted from the first floor reply in first page
            if (data.Page.CurrentPage != 1) return;

            var db = _dbContextFactory(Fid);
            using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

            var parentThreadTitle = (from t in db.Threads.AsNoTracking()
                where t.Tid == _tid select t.Title).SingleOrDefault();
            // thread title will be empty string as a fallback when the thread author haven't write title for this thread
            if (parentThreadTitle != "") return;
            var newTitle = data.PostList.FirstOrDefault(r => r.Floor == 1)?.Title;
            if (newTitle == null) return;

            db.Attach(new ThreadPost {Tid = _tid, Title = newTitle})
                .Property(t => t.Title).IsModified = true;
            if (db.SaveChanges() != 1) // do not touch UpdateAt field for the accuracy of time field in thread revisions
                throw new DbUpdateException(
                    $"Parent thread title \"{newTitle}\" completion for tid {_tid} has failed.");
            transaction.Commit();
        }

        protected override void PostCommitSaveHook(SaverChangeSet<ReplyPost> savedPosts) =>
            savedPosts.NewlyAdded.ForEach(r => _pusher.PushPost(Fid, "replies", r.Pid, r.Content));
    }
}
