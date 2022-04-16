using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using Autofac;
using Autofac.Features.Indexed;
using Google.Protobuf;
using Microsoft.Extensions.Logging;
using TbClient.Api.Response;
using Page = System.UInt32;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;

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
        private readonly UserParserAndSaver _users;
        private readonly ConcurrentDictionary<ulong, TPost> _posts = new();
        private readonly Fid _fid;
        private readonly ClientRequesterTcs _requesterTcs;
        private readonly CrawlerLocks _locks; // singleton for every derived class
        private readonly ulong _lockIndex;

        protected BaseCrawlFacade(ILogger<BaseCrawlFacade<TPost, TResponse, TPostProtoBuf, TCrawler>> logger,
            BaseCrawler<TResponse, TPostProtoBuf> crawler,
            IParser<TPost, TPostProtoBuf> parser, BaseSaver<TPost> saver, UserParserAndSaver users,
            ClientRequesterTcs requesterTcs, (CrawlerLocks, ulong) lockAndIndex, Fid fid)
        {
            _logger = logger;
            _crawler = crawler;
            _parser = parser;
            _saver = saver;
            _users = users;
            _requesterTcs = requesterTcs;
            (_locks, _lockIndex) = lockAndIndex;
            _fid = fid;
        }

        public void SavePosts<TPostRevision>(out IEnumerable<TPostRevision> postRevisions) where TPostRevision : PostRevision
        {
            using var scope = Program.Autofac.BeginLifetimeScope();
            var db = scope.Resolve<TbmDbContext.New>()(_fid);
            using var transaction = db.Database.BeginTransaction();
            _saver.SavePosts(db);
            _users.SaveUsers(db);
            db.SaveChanges();
            transaction.Commit();
            postRevisions = db.Set<TPostRevision>().Local.Select(i => (TPostRevision)i.Clone()).ToList();
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

        protected virtual void ValidateThenParse((TResponse, CrawlRequestFlag) responseAndFlag) =>
            _parser.ParsePosts(responseAndFlag.Item2, _crawler.GetValidPosts(responseAndFlag.Item1), _posts, _users);

        private Task CrawlPages(IEnumerable<Page> pages) =>
            Task.WhenAll(_locks.AcquireRange(_lockIndex, pages).Shuffle().Select(async page =>
                await CatchCrawlException(async () => (await _crawler.CrawlSinglePage(page)).ForEach(ValidateThenParse), page)
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
                e.Data["fid"] = _fid;
                _logger.Log(e is TiebaException ? LogLevel.Warning : LogLevel.Error, _crawler.FillExceptionData(e), "exception");
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
