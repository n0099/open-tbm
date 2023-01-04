namespace tbm.Crawler.Tieba.Crawl.Facade
{
    public abstract class BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler> : IDisposable
        where TPost : class, IPost where TCrawler : BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse> where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        private readonly ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> _logger;
        private readonly TbmDbContext.New _dbContextFactory;
        private readonly BaseCrawler<TResponse, TPostProtoBuf> _crawler;
        private readonly BaseParser<TPost, TPostProtoBuf> _parser;
        private readonly BaseSaver<TPost> _saver;
        protected UserParserAndSaver Users { get; }
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly CrawlerLocks _locks; // singleton for every derived class
        private readonly CrawlerLocks.LockId _lockId;
        private ExceptionHandler _exceptionHandler = _ => { };
        public delegate void ExceptionHandler(Exception ex);

        protected uint Fid { get; }
        protected ConcurrentDictionary<ulong, TPost> ParsedPosts { get; } = new();
        private readonly HashSet<Page> _lockingPages = new();

        protected BaseCrawlFacade(
            ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> logger,
            TbmDbContext.New dbContextFactory,
            BaseCrawler<TResponse, TPostProtoBuf> crawler,
            BaseParser<TPost, TPostProtoBuf> parser,
            Func<ConcurrentDictionary<PostId, TPost>, BaseSaver<TPost>> saverFactory,
            UserParserAndSaver users,
            ClientRequesterTcs requesterTcs,
            (CrawlerLocks, CrawlerLocks.LockId) lockAndId,
            Fid fid)
        {
            _logger = logger;
            _dbContextFactory = dbContextFactory;
            _crawler = crawler;
            _parser = parser;
            _saver = saverFactory(ParsedPosts);
            Users = users;
            _requesterTcs = requesterTcs;
            (_locks, _lockId) = lockAndId;
            Fid = fid;
        }

        ~BaseCrawlFacade() => Dispose();

        public void Dispose()
        {
            GC.SuppressFinalize(this);
            _locks.ReleaseRange(_lockId, _lockingPages);
        }

        protected virtual void BeforeCommitSaveHook(TbmDbContext db) { }
        protected virtual void PostCommitSaveHook(SaverChangeSet<TPost> savedPosts) { }

        public SaverChangeSet<TPost>? SaveCrawled()
        {
            var db = _dbContextFactory(Fid);
            using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

            var savedPosts = ParsedPosts.IsEmpty ? null : _saver.SavePosts(db);
            Users.SaveUsers(db, _saver);
            BeforeCommitSaveHook(db);
            try
            {
                _ = db.SaveChangesWithTimestamp();
                transaction.Commit();
                if (savedPosts != null) PostCommitSaveHook(savedPosts);
            }
            finally
            {
                _saver.PostSaveHook();
                Users.PostSaveHook();
            }
            return savedPosts;
        }

        public async Task<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>>
            CrawlPageRange(Page startPage, Page endPage = Page.MaxValue)
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
                var startPageResponse = await _crawler.CrawlSinglePage(startPage);
                startPageResponse.ForEach(ValidateThenParse);

                var maxPage = startPageResponse
                    .Select(r => _crawler.GetResponsePage(r.Result))
                    .Max(p => (Page?)p?.TotalPage);
                endPage = Math.Min(endPage, maxPage ?? Page.MaxValue);
            }, startPage, 0);

            if (!isStartPageCrawlFailed)
            {
                var pagesAfterStart = Enumerable.Range(
                    (int)(startPage + 1),
                    (int)(endPage - startPage)).ToList();
                if (pagesAfterStart.Any()) await CrawlPages(pagesAfterStart.Select(i => (Page)i));
            }
            return this;
        }

        private Task CrawlPages(IEnumerable<Page> pages, Func<Page, FailureCount>? previousFailureCountSelector = null)
        {
            var pagesList = pages.ToList();
            var acquiredLocks = _locks.AcquireRange(_lockId, pagesList).ToList();
            if (!acquiredLocks.Any())
            {
                var pagesText = Enumerable.Range((int)pagesList[0], (int)pagesList[^1])
                    .Select(i => (Page)i).SequenceEqual(pagesList)
                        ? $"within the range [{pagesList[0]}-{pagesList[^1]}]"
                        : JsonSerializer.Serialize(pagesList);
                _logger.LogInformation("Cannot crawl any page within {} for lock type {}, id {} since they've already been locked",
                    pagesText, _locks.LockType, _lockId);
            }
            _lockingPages.UnionWith(acquiredLocks);

            return Task.WhenAll(acquiredLocks.Shuffle()
                .Select(page => LogException(
                    async () => (await _crawler.CrawlSinglePage(page)).ForEach(ValidateThenParse),
                    page, previousFailureCountSelector?.Invoke(page) ?? 0)));
        }

        public async Task<SaverChangeSet<TPost>?> RetryThenSave(IEnumerable<Page> pages, Func<Page, FailureCount> failureCountSelector)
        {
            if (_lockingPages.Any()) throw new InvalidOperationException("RetryPages() can only be called once, a instance of BaseCrawlFacade shouldn't be reuse for other crawls.");
            await CrawlPages(pages, failureCountSelector);
            return SaveCrawled();
        }

        private async Task<bool> LogException(Func<Task> payload, Page page, FailureCount previousFailureCount)
        {
            try
            {
                await payload();
                return false;
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

        public BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler> AddExceptionHandler(ExceptionHandler handler)
        {
            _exceptionHandler += handler;
            return this;
        }

        private void ValidateThenParse(BaseCrawler<TResponse, TPostProtoBuf>.Response responseTuple)
        {
            var (response, flag) = responseTuple;
            var posts = _crawler.GetValidPosts(response, flag);
            try
            {
                _parser.ParsePosts(flag, posts, ParsedPosts, out var postsEmbeddedUsers);
                if (flag != CrawlRequestFlag.None) return;
                if (!postsEmbeddedUsers.Any() && posts.Any()) ThrowIfEmptyUsersEmbedInPosts(response);
                if (postsEmbeddedUsers.Any())
                {
                    Users.ParseUsers(postsEmbeddedUsers);
                    ParsePostsEmbeddedUsers(postsEmbeddedUsers, posts);
                }
            }
            finally
            {
                PostParseHook(response, flag);
            }
        }

        protected virtual void ThrowIfEmptyUsersEmbedInPosts(TResponse response) {}

        protected virtual void ParsePostsEmbeddedUsers
            (IEnumerable<User> usersEmbedInPosts, IEnumerable<TPostProtoBuf> postsInCurrentResponse) {}

        protected virtual void PostParseHook(TResponse response, CrawlRequestFlag flag) { }
    }
}
