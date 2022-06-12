using System.Data;

namespace tbm.Crawler
{
    public abstract class BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>
        where TPost : class, IPost where TCrawler : BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse>, new() where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        private readonly ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> _logger;
        private readonly BaseCrawler<TResponse, TPostProtoBuf> _crawler;
        private readonly BaseParser<TPost, TPostProtoBuf> _parser;
        private readonly BaseSaver<TPost> _saver;
        protected readonly UserParserAndSaver Users;
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly CrawlerLocks _locks; // singleton for every derived class
        private readonly FidOrPostId _lockIndex;

        protected readonly ConcurrentDictionary<PostId, TPost> ParsedPosts = new();
        public readonly ConcurrentDictionary<Page, (TPost First, TPost Last)> FirstAndLastPostInPages = new();
        protected readonly Fid Fid;

        protected BaseCrawlFacade(
            ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> logger,
            BaseCrawler<TResponse, TPostProtoBuf> crawler,
            BaseParser<TPost, TPostProtoBuf> parser,
            Func<ConcurrentDictionary<PostId, TPost>, Fid, BaseSaver<TPost>> saverFactory,
            UserParserAndSaver users,
            ClientRequesterTcs requesterTcs,
            (CrawlerLocks, FidOrPostId) lockAndIndex,
            Fid fid)
        {
            _logger = logger;
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
            using var scope = Program.Autofac.BeginLifetimeScope();
            var db = scope.Resolve<TbmDbContext.New>()(Fid);
            using var transaction = db.Database.BeginTransaction(IsolationLevel.ReadCommitted);

            var savedPosts = ParsedPosts.IsEmpty ? null : _saver.SavePosts(db);
            var savedUsersId = Users.SaveUsers(db, _saver);
            try
            {
                _ = db.SaveChanges();
                transaction.Commit();
            }
            finally
            {
                if (savedUsersId != null) Users.ReleaseLocks(savedUsersId);
            }
            return savedPosts;
        }

        public async Task<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>>
            CrawlPageRange(Page startPage, Page endPage = Page.MaxValue)
        { // cancel when startPage is already locked
            if (!_locks.AcquireRange(_lockIndex, new[] {startPage}).Any()) return this;
            var isCrawlFailed = await CatchCrawlException(async () =>
            {
                var startPageResponse = await _crawler.CrawlSinglePage(startPage);
                startPageResponse.ForEach(ValidateThenParse);

                var maxPage = startPageResponse.Select(i => _crawler.GetPageFromResponse(i.Item1)).Max(i => (Page)i.TotalPage);
                endPage = Math.Min(endPage, maxPage);
            }, startPage, 0);

            if (!isCrawlFailed) await CrawlPages(
                Enumerable.Range((int)(startPage + 1),
                    (int)(endPage - startPage)).Select(i => (Page)i));
            return this;
        }

        private Task CrawlPages(IEnumerable<Page> pages, Func<Page, FailedCount>? previousFailedCountSelector = null) =>
            Task.WhenAll(_locks.AcquireRange(_lockIndex, pages).Shuffle().Select(page =>
                CatchCrawlException(
                    async () => (await _crawler.CrawlSinglePage(page)).ForEach(ValidateThenParse),
                    page, previousFailedCountSelector?.Invoke(page) ?? 0)
            ));

        public Task RetryPages(List<CrawlerLocks.PageAndFailedCount> pageAndFailedCountRecords)
        {
            var pagesNum = pageAndFailedCountRecords.Select(i => i.Page);
            FailedCount FailedCountSelector(Page p) => pageAndFailedCountRecords.First(i => i.Page == p).FailedCount;
            return CrawlPages(pagesNum, FailedCountSelector);
        }

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
                    _logger.LogWarning("TiebaException: {} {}", e.Message, JsonSerializer.Serialize(e.Data));
                else
                    _logger.LogError(e, "Exception");
                if (e is not TiebaException {ShouldRetry: false})
                    _locks.AcquireFailed(_lockIndex, page, (FailedCount)(previousFailedCount + 1));

                _requesterTcs.Decrease();
                return true;
            }
            finally
            {
                _locks.ReleaseLock(_lockIndex, page);
            }
        }

        private void ValidateThenParse((TResponse, CrawlRequestFlag, Page) responseTuple)
        {
            var (response, flag, page) = responseTuple;
            var posts = _crawler.GetValidPosts(response);
            try
            {
                var usersStoreUnderPost = new List<User>(0); // creating a list with empty initial buffer is fast
                FirstAndLastPostInPages.SetIfNotNull(page, _parser.ParsePosts(flag, posts, ParsedPosts, usersStoreUnderPost));
                if (usersStoreUnderPost.Any()) Users.ParseUsers(usersStoreUnderPost);
            }
            finally
            {
                PostParseCallback((response, flag), posts);
            }
        }

        protected virtual void PostParseCallback((TResponse, CrawlRequestFlag) responseAndFlag, IList<TPostProtoBuf> posts) { }
    }
}
