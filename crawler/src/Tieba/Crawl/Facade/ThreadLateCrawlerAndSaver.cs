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
            _locks = locks["threadLate"]("threadLate");
        }

        public async Task Crawl()
        {
            var threads = await Task.WhenAll(_threadsId.Select(async tid =>
            {
                if (!_locks.AcquireRange(tid, new[] {(Page)1}).Any()) return null;
                try
                {
                    var json = await _requester.RequestJson("c/f/pb/page", "8.8.8.8",
                        new()
                        {
                            {"kz", tid.ToString()},
                            {"pn", "1"},
                            {"rn", "2"} // have to be at least 2, since response will always be error code 29 and msg "这个楼层可能已被删除啦，去看看其他贴子吧" with rn=1
                        });
                    if (json.GetStrProp("error_code") != "0") throw new TiebaException("Error from tieba client") {Data = {{"raw", json}}};
                    var thread = json.GetProperty("thread");
                    return new ThreadPost
                    {
                        Tid = Tid.Parse(thread.GetStrProp("id")),
                        AuthorPhoneType = thread.GetProperty("thread_info").GetStrProp("phone_type").NullIfWhiteSpace()
                    };
                }
                catch (Exception e)
                { // below is similar with BaseCrawlFacade.CatchCrawlException()
                    e.Data["fid"] = _fid;
                    e.Data["tid"] = tid;
                    e = e.ExtractInnerExceptionsData();

                    if (e is TiebaException)
                        _logger.LogWarning("TiebaException: {} {}", e.Message, JsonSerializer.Serialize(e.Data));
                    else
                        _logger.LogError(e, "Exception");
                    if (e is not TiebaException {ShouldRetry: false})
                        _locks.AcquireFailed(tid, 1);

                    _requesterTcs.Decrease();
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

            _ = db.SaveChangesWithoutTimestamping(); // do not touch UpdateAt field for the accuracy of time field in thread revisions
            transaction.Commit();
        }
    }
}
