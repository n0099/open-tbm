using System.Data;

namespace tbm.Crawler
{
    public abstract class BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>
        where TPost : class, IPost where TCrawler : BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse>, new() where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        private readonly ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> _logger;
        private readonly TbmDbContext.New _dbContextFactory;
        private readonly BaseCrawler<TResponse, TPostProtoBuf> _crawler;
        private readonly BaseParser<TPost, TPostProtoBuf> _parser;
        private readonly BaseSaver<TPost> _saver;
        protected readonly UserParserAndSaver Users;
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly CrawlerLocks _locks; // singleton for every derived class
        private readonly FidOrPostId _lockIndex;
        protected readonly Fid Fid;

        protected readonly ConcurrentDictionary<PostId, TPost> ParsedPosts = new();
        private readonly HashSet<Page> _lockingPages = new();

        protected BaseCrawlFacade(
            ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> logger,
            TbmDbContext.New dbContextFactory,
            BaseCrawler<TResponse, TPostProtoBuf> crawler,
            BaseParser<TPost, TPostProtoBuf> parser,
            Func<ConcurrentDictionary<PostId, TPost>, Fid, BaseSaver<TPost>> saverFactory,
            UserParserAndSaver users,
            ClientRequesterTcs requesterTcs,
            (CrawlerLocks, FidOrPostId) lockAndIndex,
            Fid fid)
        {
            _logger = logger;
            _dbContextFactory = dbContextFactory;
            _crawler = crawler;
            _parser = parser;
            _saver = saverFactory(ParsedPosts, fid);
            Users = users;
            _requesterTcs = requesterTcs;
            (_locks, _lockIndex) = lockAndIndex;
            Fid = fid;
        }

        public SaverChangeSet<TPost>? SavePosts()
        {
            var db = _dbContextFactory(Fid);
            using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

            var savedPosts = ParsedPosts.IsEmpty ? null : _saver.SavePosts(db);
            Users.SaveUsers(db, _saver);
            try
            {
                _ = db.SaveChangesWithTimestamping();
                transaction.Commit();
            }
            finally
            {
                _saver.PostSaveCallback();
                Users.PostSaveCallback();
            }
            _locks.ReleaseRange(_lockIndex, _lockingPages);
            return savedPosts;
        }

        public async Task<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>>
            CrawlPageRange(Page startPage, Page endPage = Page.MaxValue)
        { // cancel when startPage is already locked
            if (_lockingPages.Any()) throw new("CrawlPageRange() can only be called once, a instance of BaseCrawlFacade shouldn't be reuse for other crawls.");
            var acquiredLocks = _locks.AcquireRange(_lockIndex, new[] {startPage}).ToHashSet();
            if (!acquiredLocks.Any()) _logger.LogInformation("Can't crawl any page within the range [{}-{}] for lock index {} since they've already been locked.", startPage, endPage, _lockIndex);
            _lockingPages.UnionWith(acquiredLocks);

            var isStartPageCrawlFailed = await CatchCrawlException(async () =>
            {
                var startPageResponse = await _crawler.CrawlSinglePage(startPage);
                startPageResponse.ForEach(ValidateThenParse);

                var maxPage = startPageResponse.Select(i => _crawler.GetPageFromResponse(i.Result)).Max(i => (Page?)i?.TotalPage);
                endPage = Math.Min(endPage, maxPage ?? Page.MaxValue);
            }, startPage, 0);

            if (!isStartPageCrawlFailed)
            {
                var pagesAfterStart = Enumerable.Range((int)(startPage + 1), (int)(endPage - startPage)).ToList();
                if (pagesAfterStart.Any()) await CrawlPages(pagesAfterStart.Select(i => (Page)i));
            }
            return this;
        }

        private Task CrawlPages(IEnumerable<Page> pages, Func<Page, FailedCount>? previousFailedCountSelector = null)
        {
            pages = pages.ToList();
            var acquiredLocks = _locks.AcquireRange(_lockIndex, pages).ToList();
            if (!acquiredLocks.Any()) _logger.LogInformation("Can't crawl any page within {} for lock index {} since they've already been locked.", JsonSerializer.Serialize(pages), _lockIndex);
            _lockingPages.UnionWith(acquiredLocks);

            return Task.WhenAll(acquiredLocks.Shuffle().Select(page =>
                CatchCrawlException(
                    async () => (await _crawler.CrawlSinglePage(page)).ForEach(ValidateThenParse),
                    page, previousFailedCountSelector?.Invoke(page) ?? 0)));
        }

        public Task RetryPages(IEnumerable<Page> pages, Func<Page, FailedCount> failedCountSelector) =>
            _lockingPages.Any()
                ? CrawlPages(pages, failedCountSelector)
                : throw new("RetryPages() can only be called once, a instance of BaseCrawlFacade shouldn't be reuse for other crawls.");

        private async Task<bool> CatchCrawlException(Func<Task> callback, Page page, FailedCount previousFailedCount)
        {
            try
            {
                await callback();
                return false;
            }
            catch (Exception e)
            {
                e.Data["page"] = page;
                e.Data["fid"] = Fid;
                e = _crawler.FillExceptionData(e).ExtractInnerExceptionsData();

                if (e is TiebaException)
                    _logger.LogWarning("TiebaException: {} {}", string.Join(' ', e.GetInnerExceptions().Select(ex => ex.Message)), Helper.UnescapedJsonSerialize(e.Data));
                else
                    _logger.LogError(e, "Exception");
                if (e is not TiebaException {ShouldRetry: false})
                {
                    _locks.AcquireFailed(_lockIndex, page, (FailedCount)(previousFailedCount + 1));
                    _requesterTcs.Decrease();
                }
                return true;
            }
        }

        private void ValidateThenParse(BaseCrawler<TResponse, TPostProtoBuf>.Response responseTuple)
        {
            var (response, flag) = responseTuple;
            var posts = _crawler.GetValidPosts(response, flag);
            try
            {
                _parser.ParsePosts(flag, posts, ParsedPosts, out var usersStoreUnderPost);
                // currently only sub reply parser will return with users under every sub reply
                if (usersStoreUnderPost.Any()) Users.ParseUsers(usersStoreUnderPost);
            }
            finally
            {
                PostParseCallback(response, flag);
            }
        }

        protected virtual void PostParseCallback(TResponse response, CrawlRequestFlag flag) { }
    }
}
