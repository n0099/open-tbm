namespace tbm.Crawler
{
    public class ThreadLateCrawlerAndSaver
    {
        public record TidAndFailedCount(Tid Tid, FailedCount FailedCount);

        private readonly ILogger<ThreadLateCrawlerAndSaver> _logger;
        private readonly TbmDbContext.New _dbContextFactory;
        private readonly ClientRequester _requester;
        private readonly Fid _fid;
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly CrawlerLocks _locks; // singleton

        public delegate ThreadLateCrawlerAndSaver New(Fid fid);

        public ThreadLateCrawlerAndSaver(
            ILogger<ThreadLateCrawlerAndSaver> logger,
            TbmDbContext.New dbContextFactory,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            Fid fid)
        {
            _logger = logger;
            _dbContextFactory = dbContextFactory;
            _requester = requester;
            _fid = fid;
            _requesterTcs = requesterTcs;
            _locks = locks["threadLate"]("threadLate");
        }

        public async Task Crawl(IEnumerable<TidAndFailedCount> tidAndFailedCountRecords)
        {
            var threads = await Task.WhenAll(tidAndFailedCountRecords.Select(async tidAndFailedCount =>
            {
                var tid = tidAndFailedCount.Tid;
                if (!_locks.AcquireRange(tid, new[] {(Page)1}).Any()) return null;
                try
                {
                    var json = await _requester.RequestJson("c/f/pb/page", "8.8.8.8", new()
                    {
                        {"kz", tid.ToString()},
                        {"pn", "1"},
                        {"rn", "2"} // have to be at least 2, since response will always be error code 29 and msg "这个楼层可能已被删除啦，去看看其他贴子吧" with rn=1
                    });
                    try
                    {
                        switch (json.GetStrProp("error_code"))
                        {
                            case "4" or "350008": throw new TiebaException(false, "Thread already deleted while thread late crawl.");
                            case not "0":
                                throw new TiebaException("Error from tieba client.") {Data = {{"raw", json}}};
                        }
                        var thread = json.GetProperty("thread");
                        if (thread.GetProperty("thread_info").TryGetProperty("phone_type", out var phoneType))
                        {
                            return new ThreadPost
                            {
                                Tid = Tid.Parse(thread.GetStrProp("id")),
                                AuthorPhoneType = phoneType.GetString().NullIfWhiteSpace()
                            };
                        }
                        else throw new TiebaException(false, "Field phone_type is missing in response.thread.thread_info, it might be a historical thread.");
                    }
                    catch (Exception e) when (e is not TiebaException)
                    {
                        e.Data["raw"] = json;
                        throw;
                    }
                }
                catch (Exception e)
                { // below is similar with BaseCrawlFacade.CatchCrawlException()
                    e.Data["fid"] = _fid;
                    e.Data["tid"] = tid;
                    e = e.ExtractInnerExceptionsData();

                    if (e is TiebaException)
                        _logger.LogWarning("TiebaException: {} {}", string.Join(' ', e.GetInnerExceptions().Select(ex => ex.Message)), Helper.UnescapedJsonSerialize(e.Data));
                    else
                        _logger.LogError(e, "Exception");
                    if (e is not TiebaException {ShouldRetry: false})
                    {
                        _locks.AcquireFailed(tid, 1, tidAndFailedCount.FailedCount);
                        _requesterTcs.Decrease();
                    }
                    return null;
                }
                finally
                {
                    _locks.ReleaseLock(tid, 1);
                }
            }));

            var db = _dbContextFactory(_fid);
            await using var transaction = await db.Database.BeginTransactionAsync();

            db.AttachRange(threads.OfType<ThreadPost>());
            db.ChangeTracker.Entries<ThreadPost>().ForEach(e => e.Property(t => t.AuthorPhoneType).IsModified = true);

            _ = db.SaveChangesWithoutTimestamping(); // do not touch UpdateAt field for the accuracy of time field in thread revisions
            await transaction.CommitAsync();
        }
    }
}
