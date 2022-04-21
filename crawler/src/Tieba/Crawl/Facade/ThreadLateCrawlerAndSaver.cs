using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.Crawler
{
    public class ThreadLateCrawlerAndSaver
    {
        private readonly ILogger<ThreadLateCrawlerAndSaver> _logger;
        private readonly ClientRequester _requester;
        private readonly Fid _fid;
        private readonly IEnumerable<Tid> _threadsId;
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly CrawlerLocks _locks; // singleton

        public delegate ThreadLateCrawlerAndSaver New(Fid fid, IEnumerable<Tid> threadsId);

        public ThreadLateCrawlerAndSaver(
            ILogger<ThreadLateCrawlerAndSaver> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid, IEnumerable<Tid> threadsId)
        {
            _logger = logger;
            _requester = requester;
            _fid = fid;
            _threadsId = threadsId;
            _requesterTcs = requesterTcs;
            _locks = locks["threadLateSave"]("threadLateSave");
        }

        private async void Crawl()
        {
            var threads = await Task.WhenAll(_threadsId.Select(async tid =>
            {
                if (!_locks.AcquireRange(tid, new List<Page> {1}).Any()) return null;
                try
                {
                    var json = await _requester.RequestJson("http://c.tieba.baidu.com/c/f/pb/page",
                        new Dictionary<string, string>
                        {
                            {"kz", tid.ToString()},
                            {"pn", "1"},
                            {"rn", "1"}
                        }, "8.8.8.8");
                    var thread = json.GetProperty("thread");
                    return new ThreadPost
                    {
                        Tid = Tid.Parse(thread.GetStrProp("id")),
                        AuthorPhoneType = thread.GetStrProp("phone_type")
                    };
                }
                catch (Exception e)
                {
                    e.Data["fid"] = _fid;
                    e.Data["tid"] = tid;
                    e = e.ExtractInnerExceptionsData();

                    _logger.Log(e is TiebaException ? LogLevel.Warning : LogLevel.Error, e, "exception");
                    _requesterTcs.Decrease();
                    _locks.AcquireFailed(tid, 1);
                    return null;
                }
                finally
                {
                    _locks.ReleaseLock(tid, 1);
                }
            }));

            await using var scope = Program.Autofac.BeginLifetimeScope();
            var db = scope.Resolve<TbmDbContext.New>()(_fid);
            await using var transaction = db.Database.BeginTransaction();

            db.AttachRange(threads.OfType<ThreadPost>());
            db.ChangeTracker.Entries<ThreadPost>().ForEach(e => e.Property(t => t.AuthorPhoneType).IsModified = true);

            _ = db.SaveChangesWithoutTimestamping();
            transaction.Commit();
        }
    }
}
