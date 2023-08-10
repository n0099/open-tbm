namespace tbm.Crawler.Tieba.Crawl;

public class ThreadLateCrawlerAndSaver(
    ILogger<ThreadLateCrawlerAndSaver> logger,
    CrawlerDbContext.New dbContextFactory,
    ClientRequester requester,
    ClientRequesterTcs requesterTcs,
    IIndex<string, CrawlerLocks> locks,
    Fid fid)
{
    public delegate ThreadLateCrawlerAndSaver New(Fid fid);

    private readonly CrawlerLocks _locks = locks["threadLate"]; // singleton

    public async Task CrawlThenSave(Dictionary<Tid, FailureCount> failureCountsKeyByTid, CancellationToken stoppingToken = default)
    {
        var threads = await Task.WhenAll(
            failureCountsKeyByTid.Select(pair => CrawlThread(pair.Key, pair.Value, stoppingToken)));

        var db = dbContextFactory(fid);
        await using var transaction = await db.Database.BeginTransactionAsync(stoppingToken);

        db.AttachRange(threads.OfType<ThreadPost>()); // remove nulls due to exception
        db.ChangeTracker.Entries<ThreadPost>()
            .ForEach(ee => ee.Property(th => th.AuthorPhoneType).IsModified = true);

        _ = await db.SaveChangesAsync(stoppingToken); // do not touch UpdateAt field for the accuracy of time field in thread revisions
        await transaction.CommitAsync(stoppingToken);
    }

    private async Task<ThreadPost?> CrawlThread(Tid tid, FailureCount failureCount, CancellationToken stoppingToken = default)
    {
        var crawlerLockId = new CrawlerLocks.LockId(fid, tid);
        if (!_locks.AcquireRange(crawlerLockId, new[] {(Page)1}).Any()) return null;
        try
        {
            var json = await requester.RequestJson(
                $"{ClientRequester.LegacyClientApiDomain}/c/f/pb/page", "8.8.8.8", new()
                {
                    {"kz", tid.ToString()},
                    {"pn", "1"},
                    // rn have to be at least 2
                    // since response will always be error code 29 and msg "这个楼层可能已被删除啦，去看看其他贴子吧" with rn=1
                    {"rn", "2"}
                }, stoppingToken);
            try
            {
                var errorCodeProp = json.GetProperty("error_code");
                Func<(int ErrorCode, bool IsErrorCodeParsed)> tryGetErrorCode = errorCodeProp.ValueKind switch
                { // https://github.com/MoeNetwork/Tieba-Cloud-Sign/pull/220#issuecomment-1367570540
                    JsonValueKind.Number => () =>
                    {
                        var r = errorCodeProp.TryGetInt32(out var p);
                        return (p, r);
                    },
                    JsonValueKind.String => () =>
                    { // https://stackoverflow.com/questions/62100000/why-doesnt-system-text-json-jsonelement-have-trygetstring-or-trygetboolean/62100246#62100246
                        var r = int.TryParse(errorCodeProp.GetString(), out var p);
                        return (p, r);
                    },
                    _ => () => (0, false)
                };

                var (errorCode, isErrorCodeParsed) = tryGetErrorCode();
                if (!isErrorCodeParsed) throw new TiebaException(
                        "Cannot get field \"error_code\" or parse its value from the response of tieba json api.")
                    {Data = {{"raw", json}, {"rawErrorCode", errorCodeProp.GetRawText()}}};
                switch (errorCode)
                {
                    case 4 or 350008:
                        throw new TiebaException(shouldRetry: false,
                            "Thread already deleted while thread late crawl.");
                    case not 0:
                        throw new TiebaException("Error from tieba client.") {Data = {{"raw", json}}};
                }

                var thread = json.GetProperty("thread");
                return thread.TryGetProperty("thread_info", out var threadInfo)
                    ? threadInfo.TryGetProperty("phone_type", out var phoneType)
                        ? new ThreadPost
                        {
                            Tid = Tid.Parse(thread.GetStrProp("id")),
                            AuthorPhoneType = phoneType.GetString().NullIfEmpty()
                        }
                        : throw new TiebaException(shouldRetry: false,
                            "Field phone_type is missing in response json.thread.thread_info, it might be a historical thread.")
                    : null; // silent fail without any retry since the field `json.thread.thread_info`
                            // might not exists in current and upcoming responses
            }
            catch (Exception e) when (e is not TiebaException)
            {
                e.Data["raw"] = json;
                throw;
            }
        }
        catch (Exception e)
        { // below is similar with BaseCrawlFacade.SilenceException()
            e.Data["fid"] = fid;
            e.Data["tid"] = tid;
            e = e.ExtractInnerExceptionsData();

            if (e is TiebaException)
                logger.LogWarning("TiebaException: {} {}",
                    string.Join(' ', e.GetInnerExceptions().Select(ex => ex.Message)),
                    Helper.UnescapedJsonSerialize(e.Data));
            else
                logger.LogError(e, "Exception");
            if (e is not TiebaException {ShouldRetry: false})
            {
                _locks.AcquireFailed(crawlerLockId, page: 1, failureCount);
                requesterTcs.Decrease();
            }
            return null;
        }
        finally
        {
            _locks.ReleaseRange(crawlerLockId, new Page[] {1});
        }
    }
}
