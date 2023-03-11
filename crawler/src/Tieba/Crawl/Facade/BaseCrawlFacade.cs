namespace tbm.Crawler.Tieba.Crawl.Facade;

public abstract class BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf, TCrawler> : IDisposable
    where TPost : class, IPost
    where TBaseRevision : class, IRevision
    where TResponse : IMessage<TResponse>
    where TPostProtoBuf : IMessage<TPostProtoBuf>
    where TCrawler : BaseCrawler<TResponse, TPostProtoBuf>
{
    private readonly ILogger<BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf, TCrawler>> _logger;
    private readonly TbmDbContext.New _dbContextFactory;
    private readonly BaseCrawler<TResponse, TPostProtoBuf> _crawler;
    private readonly BaseParser<TPost, TPostProtoBuf> _parser;
    private readonly BaseSaver<TPost, TBaseRevision> _saver;
    private readonly ClientRequesterTcs _requesterTcs;
    private readonly CrawlerLocks _locks; // singleton for every derived class
    private readonly CrawlerLocks.LockId _lockId;
    private readonly HashSet<Page> _lockingPages = new();
    private ExceptionHandler _exceptionHandler = _ => { };
    public delegate void ExceptionHandler(Exception ex);

    protected uint Fid { get; }
    protected ConcurrentDictionary<ulong, TPost> Posts { get; } = new();
    protected UserParserAndSaver Users { get; }

    protected BaseCrawlFacade(
        ILogger<BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf, TCrawler>> logger,
        TbmDbContext.New dbContextFactory,
        BaseCrawler<TResponse, TPostProtoBuf> crawler,
        BaseParser<TPost, TPostProtoBuf> parser,
        Func<ConcurrentDictionary<PostId, TPost>, BaseSaver<TPost, TBaseRevision>> saverFactory,
        UserParserAndSaver users,
        ClientRequesterTcs requesterTcs,
        (CrawlerLocks, CrawlerLocks.LockId) lockAndId,
        Fid fid)
    {
        _logger = logger;
        _dbContextFactory = dbContextFactory;
        _crawler = crawler;
        _parser = parser;
        _saver = saverFactory(Posts);
        Users = users;
        _requesterTcs = requesterTcs;
        (_locks, _lockId) = lockAndId;
        Fid = fid;
    }

    public void Dispose() => _locks.ReleaseRange(_lockId, _lockingPages);

    protected virtual void BeforeCommitSaveHook(TbmDbContext db) { }
    protected virtual void PostCommitSaveHook(SaverChangeSet<TPost> savedPosts, CancellationToken stoppingToken = default) { }

    public SaverChangeSet<TPost>? SaveCrawled(CancellationToken stoppingToken = default)
    {
        Posts.Values.Where(p => p.AuthorUid == 0).ForEach(p => _logger.LogError(
            "Value of IPost.AuthorUid is the protoBuf default value 0, post={}", Helper.UnescapedJsonSerialize(p)));
        var db = _dbContextFactory(Fid);
        using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

        var savedPosts = Posts.IsEmpty ? null : _saver.SavePosts(db);
        Users.SaveUsers(db, _saver.PostType, _saver.TiebaUserFieldChangeIgnorance);
        BeforeCommitSaveHook(db);
        try
        {
            db.TimestampingEntities();
            _ = db.SaveChanges();
            transaction.Commit();
            if (savedPosts != null) PostCommitSaveHook(savedPosts, stoppingToken);
        }
        finally
        {
            _saver.OnPostSaveEvent();
            Users.PostSaveHook();
        }
        return savedPosts;
    }

    public async Task<BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf, TCrawler>>
        CrawlPageRange(Page startPage, Page endPage = Page.MaxValue, CancellationToken stoppingToken = default)
    { // cancel when startPage is already locked
        if (_lockingPages.Any()) throw new InvalidOperationException(
            "CrawlPageRange() can only be called once, a instance of BaseCrawlFacade shouldn't be reuse for other crawls.");
        var acquiredLocks = _locks.AcquireRange(_lockId, new[] {startPage}).ToHashSet();
        if (!acquiredLocks.Any()) _logger.LogInformation(
            "Cannot crawl any page within the range [{}-{}] for lock type {}, id {} since they've already been locked",
            startPage, endPage, _locks.LockType, _lockId);
        _lockingPages.UnionWith(acquiredLocks);

        var isStartPageCrawlFailed = await LogException(async () =>
        {
            var startPageResponse = await _crawler.CrawlSinglePage(startPage, stoppingToken);
            startPageResponse.ForEach(ValidateThenParse);

            var maxPage = startPageResponse
                .Select(response => _crawler.GetResponsePage(response.Result))
                .Max(page => (Page?)page?.TotalPage);
            endPage = Math.Min(endPage, maxPage ?? Page.MaxValue);
        }, startPage, 0, stoppingToken);

        if (!isStartPageCrawlFailed)
        {
            var pagesAfterStart = Enumerable.Range(
                (int)(startPage + 1),
                (int)(endPage - startPage)).ToList();
            if (pagesAfterStart.Any())
                await CrawlPages(pagesAfterStart.Select(page => (Page)page).ToList(), stoppingToken: stoppingToken);
        }
        return this;
    }

    private Task CrawlPages(IList<Page> pages,
        Func<Page, FailureCount>? previousFailureCountSelector = null, CancellationToken stoppingToken = default)
    {
        var acquiredLocks = _locks.AcquireRange(_lockId, pages).ToList();
        if (!acquiredLocks.Any())
        {
            var pagesText = Enumerable
                .Range((int)pages[0], (int)pages[^1])
                .Select(page => (Page)page)
                .SequenceEqual(pages)
                ? $"within the range [{pages[0]}-{pages[^1]}]"
                : JsonSerializer.Serialize(pages);
            _logger.LogInformation("Cannot crawl any page within {} for lock type {}, id {} since they've already been locked",
                pagesText, _locks.LockType, _lockId);
        }
        _lockingPages.UnionWith(acquiredLocks);

        return Task.WhenAll(acquiredLocks.Shuffle()
            .Select(page => LogException(
                async () => (await _crawler.CrawlSinglePage(page, stoppingToken)).ForEach(ValidateThenParse),
                page, previousFailureCountSelector?.Invoke(page) ?? 0, stoppingToken)));
    }

    public async Task<SaverChangeSet<TPost>?> RetryThenSave(IList<Page> pages,
        Func<Page, FailureCount> failureCountSelector, CancellationToken stoppingToken = default)
    {
        if (_lockingPages.Any()) throw new InvalidOperationException(
            "RetryPages() can only be called once, a instance of BaseCrawlFacade shouldn't be reuse for other crawls.");
        await CrawlPages(pages, failureCountSelector, stoppingToken);
        return SaveCrawled(stoppingToken);
    }

    private async Task<bool> LogException(Func<Task> payload, Page page,
        FailureCount previousFailureCount, CancellationToken stoppingToken = default)
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
            e = _crawler.FillExceptionData(e).ExtractInnerExceptionsData();

            if (e is TiebaException te)
            {
                if (!te.ShouldSilent)
                    _logger.LogWarning("TiebaException: {} {}",
                        string.Join(' ', e.GetInnerExceptions().Select(ex => ex.Message)),
                        Helper.UnescapedJsonSerialize(e.Data));
            }
            else _logger.LogError(e, "Exception");

            if (e is not TiebaException {ShouldRetry: false})
            {
                _locks.AcquireFailed(_lockId, page, (FailureCount)(previousFailureCount + 1));
                _requesterTcs.Decrease();
            }

            try
            {
                _exceptionHandler(e);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Exception");
                return true;
            }
            return true;
        }
    }

    public BaseCrawlFacade<TPost, TBaseRevision, TResponse, TPostProtoBuf, TCrawler>
        AddExceptionHandler(ExceptionHandler handler)
    {
        _exceptionHandler += handler;
        return this;
    }

    private void ValidateThenParse(BaseCrawler<TResponse, TPostProtoBuf>.Response responseTuple)
    {
        var (response, flag) = responseTuple;
        var postsInResponse = _crawler.GetValidPosts(response, flag);
        _parser.ParsePosts(flag, postsInResponse, out var parsedPostsInResponse, out var postsEmbeddedUsers);
        parsedPostsInResponse.ForEach(pair => Posts[pair.Key] = pair.Value);
        if (flag == CrawlRequestFlag.None)
        {
            if (!postsEmbeddedUsers.Any() && postsInResponse.Any()) ThrowIfEmptyUsersEmbedInPosts();
            if (postsEmbeddedUsers.Any()) Users.ParseUsers(postsEmbeddedUsers);
        }
        PostParseHook(response, flag, parsedPostsInResponse);
    }

    protected virtual void ThrowIfEmptyUsersEmbedInPosts() { }

    protected virtual void PostParseHook(TResponse response, CrawlRequestFlag flag, Dictionary<PostId, TPost> parsedPostsInResponse) { }
}
