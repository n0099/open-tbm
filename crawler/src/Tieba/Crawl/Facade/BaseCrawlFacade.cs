using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.Crawler
{
    public abstract class BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>
        where TPost : class, IPost where TCrawler : BaseCrawler<TResponse, TPostProtoBuf>
        where TResponse : IMessage<TResponse>, new() where TPostProtoBuf : IMessage<TPostProtoBuf>
    {
        private readonly ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> _logger;
        private readonly BaseCrawler<TResponse, TPostProtoBuf> _crawler;
        private readonly IParser<TPost, TPostProtoBuf> _parser;
        private readonly BaseSaver<TPost> _saver;
        protected readonly UserParserAndSaver Users;
        protected readonly ConcurrentDictionary<ulong, TPost> ParsedPosts = new();
        protected readonly Fid Fid;
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly CrawlerLocks _locks; // singleton for every derived class
        private readonly ulong _lockIndex;

        protected BaseCrawlFacade(
            ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> logger,
            BaseCrawler<TResponse, TPostProtoBuf> crawler,
            IParser<TPost, TPostProtoBuf> parser,
            Func<ConcurrentDictionary<ulong, TPost>, Fid, BaseSaver<TPost>> saver,
            UserParserAndSaver users,
            ClientRequesterTcs requesterTcs,
            (CrawlerLocks, ulong) lockAndIndex,
            Fid fid)
        {
            _logger = logger;
            _crawler = crawler;
            _parser = parser;
            _saver = saver(ParsedPosts, fid);
            Users = users;
            _requesterTcs = requesterTcs;
            (_locks, _lockIndex) = lockAndIndex;
            Fid = fid;
        }

        public void SavePosts<TPostRevision>(
            out ILookup<bool, TPost> existingOrNewLookupOnOldPosts,
            out ILookup<bool, TiebaUser> existingOrNewLookupOnOldUsers,
            out IEnumerable<TPostRevision> postRevisions)
            where TPostRevision : PostRevision
        {
            using var scope = Program.Autofac.BeginLifetimeScope();
            var db = scope.Resolve<TbmDbContext.New>()(Fid);
            using var transaction = db.Database.BeginTransaction();

            existingOrNewLookupOnOldPosts = _saver.SavePosts(db);
            existingOrNewLookupOnOldUsers = Users.SaveUsers(db);
            _ = db.SaveChanges();
            transaction.Commit();
            postRevisions = db.Set<TPostRevision>().Local.ToList();
        }

        public async Task<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>>
            CrawlPageRange(Page startPage, Page endPage = Page.MaxValue)
        { // cancel when startPage is already locked
            if (!_locks.AcquireRange(_lockIndex, new[] {startPage}).Any()) return this;
            var isCrawlFailed = await CatchCrawlException(async () =>
            {
                var startPageResponse = await _crawler.CrawlSinglePage(startPage);
                startPageResponse.ForEach(ValidateThenParse);

                var dataField = new TResponse().Descriptor.FindFieldByName("data");
                var data = startPageResponse.Select(i => (IMessage)dataField.Accessor.GetValue(i.Item1));
                var page = data.Select(i => (TbClient.Page)dataField.MessageType.FindFieldByName("page").Accessor.GetValue(i));
                endPage = Math.Min(endPage, (uint)page.Max(i => i.TotalPage));
            }, startPage);

            if (!isCrawlFailed) await CrawlPages(
                Enumerable.Range((int)(startPage + 1),
                    (int)(endPage - startPage)).Select(i => (Page)i)
            );
            return this;
        }

        private void ValidateThenParse((TResponse, CrawlRequestFlag) responseAndFlag)
        {
            var (response, flag) = responseAndFlag;
            var posts = _crawler.GetValidPosts(response);
            var usersStoreUnderPost = _parser.ParsePosts(flag, posts, ParsedPosts);
            if (usersStoreUnderPost != null) Users.ParseUsers(usersStoreUnderPost);
            PostParseCallback(responseAndFlag, posts);
        }

        protected virtual void PostParseCallback((TResponse, CrawlRequestFlag) responseAndFlag, IList<TPostProtoBuf> posts) { }

        private Task CrawlPages(IEnumerable<Page> pages) =>
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
                e = _crawler.FillExceptionData(e);
                var inner = e.InnerException;
                do
                { // recursive merge all data of exceptions into e.Data
                    if (inner == null) continue;
                    foreach (var dataKey in inner.Data.Keys)
                        e.Data[dataKey] = inner.Data[dataKey];
                    inner = inner.InnerException;
                } while (inner != null);

                _logger.Log(e is TiebaException ? LogLevel.Warning : LogLevel.Error, e, "exception");
                _requesterTcs.Decrease();
                _locks.AcquireFailed(_lockIndex, page);
                return true;
            }
            finally
            {
                _locks.ReleaseLock(_lockIndex, page);
            }
        }
    }
}
