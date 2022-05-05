using System.Data;
using Google.Protobuf.Reflection;

namespace tbm.Crawler
{
    public abstract class BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>
        where TPost : class, IPost where TCrawler : BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse>, new() where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        // cache data and page field of protoBuf instance TResponse for every derived classes: https://stackoverflow.com/questions/5851497/static-fields-in-a-base-class-and-derived-classes
        private static readonly FieldDescriptor ResponseDataField = new TResponse().Descriptor.FindFieldByName("data");
        // ReSharper disable once StaticMemberInGenericType
        private static readonly IFieldAccessor ResponsePageFieldAccessor = ResponseDataField.MessageType.FindFieldByName("page").Accessor;

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
            var savedUsersId = Users.SaveUsers(db, _saver.TiebaUserFieldsChangeIgnorance);
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

        private static TbClient.Page GetPageFromResponse(TResponse res)
        {
            var data = (IMessage)ResponseDataField.Accessor.GetValue(res);
            return (TbClient.Page)ResponsePageFieldAccessor.GetValue(data);
        }

        public async Task<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>>
            CrawlPageRange(Page startPage, Page endPage = Page.MaxValue)
        { // cancel when startPage is already locked
            if (!_locks.AcquireRange(_lockIndex, new[] {startPage}).Any()) return this;
            var isCrawlFailed = await CatchCrawlException(async () =>
            {
                var startPageResponse = await _crawler.CrawlSinglePage(startPage);
                startPageResponse.ForEach(ValidateThenParse);

                endPage = Math.Min(endPage,
                    startPageResponse.Select(i => GetPageFromResponse(i.Item1)).Max(i => (Page)i.TotalPage));
            }, startPage);

            if (!isCrawlFailed) await CrawlPages(
                Enumerable.Range((int)(startPage + 1),
                    (int)(endPage - startPage)).Select(i => (Page)i));
            return this;
        }

        public Task CrawlPages(IEnumerable<Page> pages) =>
            Task.WhenAll(_locks.AcquireRange(_lockIndex, pages).Shuffle().Select(page =>
                CatchCrawlException(async () => (await _crawler.CrawlSinglePage(page)).ForEach(ValidateThenParse), page)
            ));

        private async Task<bool> CatchCrawlException(Func<Task> callback, Page page)
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
                    _locks.AcquireFailed(_lockIndex, page);

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
