namespace tbm.Crawler.Tieba.Crawl.Facade;

#pragma warning disable S3881 // "IDisposable" should be implemented correctly
public abstract class BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf>(
#pragma warning restore S3881 // "IDisposable" should be implemented correctly
    BaseCrawler<TResponse, TPostProtoBuf> crawler,
    Fid fid,
    CrawlerLocks.LockId lockId,
    CrawlerLocks locks,
    BasePostParser<TPost, TPostProtoBuf> postParser,
    Func<ConcurrentDictionary<PostId, TPost>, BasePostSaver<TPost, TBaseRevision>> postSaverFactory,
    Func<ConcurrentDictionary<Uid, User>, UserParser> userParserFactory,
    Func<ConcurrentDictionary<Uid, User>, UserSaver> userSaverFactory)
    : IDisposable
    where TPost : class, IPost
    where TBaseRevision : class, IRevision
    where TResponse : class, IMessage<TResponse>
    where TPostProtoBuf : class, IMessage<TPostProtoBuf>
{
    private readonly HashSet<Page> _lockingPages = [];
    private readonly ConcurrentDictionary<Uid, User> _users = new();
    private UserParser? _userParser;
    private ExceptionHandler _exceptionHandler = _ => { };

    public delegate void ExceptionHandler(Exception ex);

    // ReSharper disable UnusedAutoPropertyAccessor.Global
    public required ILogger<BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf>>
        Logger { private get; init; }
    public required CrawlerDbContext.New DbContextFactory { private get; init; }
    public required ClientRequesterTcs RequesterTcs { private get; init; }

    // ReSharper restore UnusedAutoPropertyAccessor.Global
    protected Fid Fid { get; } = fid;
    protected ConcurrentDictionary<PostId, TPost> Posts { get; } = new();
    protected UserParser UserParser => _userParser ??= userParserFactory(_users);

    public virtual void Dispose()
    {
        GC.SuppressFinalize(this); // https://github.com/dotnet/roslyn-analyzers/issues/4745
        locks.ReleaseRange(lockId, _lockingPages);
    }

    public SaverChangeSet<TPost>? SaveCrawled(CancellationToken stoppingToken = default)
    {
        var db = DbContextFactory(Fid);
        using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

        var postSaver = postSaverFactory(Posts);
        var savedPosts = Posts.IsEmpty ? null : postSaver.Save(db);

        var userSaver = userSaverFactory(_users);
        userSaver.Save(db, postSaver.PostType, postSaver.UserFieldChangeIgnorance);

        BeforeCommitSaveHook(db, userSaver);
        try
        {
            db.TimestampingEntities();
            _ = db.SaveChanges();
            transaction.Commit();
            if (savedPosts != null) PostCommitSaveHook(savedPosts, stoppingToken);
        }
        finally
        {
            postSaver.OnPostSaveEvent();
            userSaver.PostSaveHook();
        }
        return savedPosts;
    }

    public async Task<BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf>>
        CrawlPageRange(Page startPage, Page endPage = Page.MaxValue, CancellationToken stoppingToken = default)
    { // cancel when startPage is already locked
        if (_lockingPages.Count != 0) ThrowHelper.ThrowInvalidOperationException(
            "CrawlPageRange() can only be called once, a instance of BaseCrawlFacade shouldn't be reuse for other crawls.");
        var acquiredLocks = locks.AcquireRange(lockId, [startPage]);
        if (acquiredLocks.Count == 0) Logger.LogInformation(
            "Cannot crawl any page within the range [{}-{}] for lock type {}, id {} since they've already been locked",
            startPage, endPage, locks.LockType, lockId);
        _lockingPages.UnionWith(acquiredLocks);

        var isStartPageCrawlFailed = await LogException(async () =>
        {
            var startPageResponse = await crawler.CrawlSinglePage(startPage, stoppingToken);
            startPageResponse.ForEach(ValidateThenParse);

            var maxPage = startPageResponse
                .Select(response => crawler.GetResponsePage(response.Result))
                .Max(page => (Page?)page?.TotalPage);
            endPage = Math.Min(endPage, maxPage ?? Page.MaxValue);
        }, startPage, previousFailureCount: 0, stoppingToken);

        // ReSharper disable once InvertIf
        if (!isStartPageCrawlFailed)
        {
            var pagesAfterStart = Enumerable.Range(
                (int)(startPage + 1),
                (int)(endPage - startPage)).ToList();
            if (pagesAfterStart.Count != 0)
                await CrawlPages(pagesAfterStart.ConvertAll(page => (Page)page), stoppingToken: stoppingToken);
        }
        return this;
    }

    public async Task<SaverChangeSet<TPost>?> RetryThenSave
        (IReadOnlyList<Page> pages, Func<Page, FailureCount> failureCountSelector, CancellationToken stoppingToken = default)
    {
        if (_lockingPages.Count != 0) ThrowHelper.ThrowInvalidOperationException(
            "RetryPages() can only be called once, a instance of BaseCrawlFacade shouldn't be reuse for other crawls.");
        await CrawlPages(pages, failureCountSelector, stoppingToken);
        return SaveCrawled(stoppingToken);
    }

    public BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf>
        AddExceptionHandler(ExceptionHandler handler)
    {
        _exceptionHandler += handler;
        return this;
    }

    protected virtual void ThrowIfEmptyUsersEmbedInPosts() { }
    protected virtual void PostParseHook(
        TResponse response,
        CrawlRequestFlag flag,
        IReadOnlyDictionary<PostId, TPost> parsedPostsInResponse) { }
    protected virtual void BeforeCommitSaveHook(CrawlerDbContext db, UserSaver userSaver) { }
    protected virtual void PostCommitSaveHook(
        SaverChangeSet<TPost> savedPosts,
        CancellationToken stoppingToken = default) { }

    private void ValidateThenParse(BaseCrawler<TResponse, TPostProtoBuf>.Response responseTuple)
    {
        var (response, flag) = responseTuple;
        var postsInResponse = crawler.GetValidPosts(response, flag);
        postParser.Parse(flag, postsInResponse, out var parsedPostsInResponse, out var postsEmbeddedUsers);
        parsedPostsInResponse.ForEach(pair => Posts[pair.Key] = pair.Value);
        if (flag == CrawlRequestFlag.None)
        {
            if (postsEmbeddedUsers.Count == 0 && postsInResponse.Count != 0) ThrowIfEmptyUsersEmbedInPosts();
            if (postsEmbeddedUsers.Count != 0) UserParser.Parse(postsEmbeddedUsers);
        }
        PostParseHook(response, flag, parsedPostsInResponse);
    }

    private async Task CrawlPages(
        IReadOnlyList<Page> pages,
        Func<Page, FailureCount>? previousFailureCountSelector = null,
        CancellationToken stoppingToken = default)
    {
        var acquiredLocks = locks.AcquireRange(lockId, pages);
        if (acquiredLocks.Count == 0)
        {
            var pagesText = Enumerable
                .Range((int)pages[0], (int)pages[^1])
                .Select(page => (Page)page)
                .SequenceEqual(pages)
                ? $"within the range [{pages[0]}-{pages[^1]}]"
                : JsonSerializer.Serialize(pages);
            Logger.LogInformation("Cannot crawl any page within {} for lock type {}, id {} since they've already been locked",
                pagesText, locks.LockType, lockId);
        }
        _lockingPages.UnionWith(acquiredLocks);

        _ = await Task.WhenAll(acquiredLocks.Shuffle()
            .Select(page => LogException(
                async () => (await crawler.CrawlSinglePage(page, stoppingToken)).ForEach(ValidateThenParse),
                page, previousFailureCountSelector?.Invoke(page) ?? 0, stoppingToken)));
    }

    private async Task<bool> LogException
        (Func<Task> payload, Page page, FailureCount previousFailureCount, CancellationToken stoppingToken = default)
    {
        try
        {
            await payload();
            return false;
        }
        catch (OperationCanceledException e) when (e.CancellationToken == stoppingToken)
        {
            throw;
        }
        catch (Exception e)
        {
            e.Data["page"] = page;
            e.Data["fid"] = Fid;
            e = crawler.FillExceptionData(e).ExtractInnerExceptionsData();

            if (e is TiebaException te)
            {
                if (!te.ShouldSilent) Logger.LogWarning("TiebaException: {} {}",
                    string.Join(' ', e.GetInnerExceptions().Select(ex => ex.Message)),
                    Helper.UnescapedJsonSerialize(e.Data));
            }
            else
            {
                Logger.LogError(e, "Exception");
            }

            if (e is not TiebaException {ShouldRetry: false})
            {
                locks.AcquireFailed(lockId, page, (FailureCount)(previousFailureCount + 1));
                RequesterTcs.Decrease();
            }

            try
            {
                _exceptionHandler(e);
            }
            catch (Exception ex)
            {
                Logger.LogError(ex, "Exception");
                return true;
            }
            return true;
        }
    }
}
