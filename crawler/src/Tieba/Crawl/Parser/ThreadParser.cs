namespace tbm.Crawler.Tieba.Crawl.Parser
{
    public class ThreadParser : BaseParser<ThreadPost, Thread>
    {
        protected override PostId PostIdSelector(ThreadPost post) => post.Tid;

        protected override bool ShouldSkipParse(CrawlRequestFlag requestFlag, IEnumerable<Thread> inPosts, ConcurrentDictionary<PostId, ThreadPost> outPosts)
        {
            var outThreads = outPosts.Values;
            Thread? GetInPostsByTid(IPost t) => inPosts.FirstOrDefault(t2 => (Tid)t2.Tid == t.Tid);
            Func<bool> testRequestFlag = requestFlag switch
            {
                CrawlRequestFlag.None => () => false,
                CrawlRequestFlag.ThreadClientVersion602 => () =>
                {
                    outThreads.Where(t => t.LatestReplierUid == null)
                        // when the thread is livepost, the last replier field will not exists in the response of tieba client 6.0.2
                        .ForEach(t => t.LatestReplierUid = GetInPostsByTid(t)?.LastReplyer?.Uid);
                    outThreads.Where(t => t.Geolocation != null) // replace with more detailed location.name in the 6.0.2 response
                        .ForEach(t => t.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(GetInPostsByTid(t)?.Location));
                    return true;
                },
                CrawlRequestFlag.ThreadClientVersion8888 => () =>
                {
                    outThreads.Where(t => t.FirstReplyPid == null)
                        .ForEach(t => t.FirstReplyPid = (Pid?)GetInPostsByTid(t)?.FirstPostId);
                    return true;
                },
                _ => throw new ArgumentOutOfRangeException(nameof(requestFlag), requestFlag, "Unexpected CrawlRequestFlag.")
            };
            return testRequestFlag();
        }

        protected override IEnumerable<ThreadPost> ParsePostsInternal(IEnumerable<Thread> inPosts, List<User> outUsers) =>
            inPosts.Select(el =>
            {
                outUsers.Add(el.Author);
                return Convert(el);
            });

        protected override ThreadPost Convert(Thread el)
        {
            var p = new ThreadPost();
            try
            {
                p.Tid = (Tid)el.Tid;
                p.ThreadType = (ulong)el.ThreadTypes;
                p.StickyType = el.IsMembertop == 1 ? "membertop" : el.IsTop == 0 ? null : "top";
                p.IsGood = (ushort?)el.IsGood.NullIfZero();
                p.TopicType = el.LivePostType.NullIfWhiteSpace();
                p.Title = el.Title;
                p.AuthorUid = el.Author.Uid;
                // value of AuthorManagerType will be write back in ThreadCrawlFacade.PostParseHook()
                p.PostTime = (uint)el.CreateTime;
                p.LatestReplyTime = (uint)el.LastTimeInt;
                // value of LatestReplierUid will be write back from the response of client version 6.0.2 by TrySkipParse()
                p.ReplyCount = (uint?)el.ReplyNum.NullIfZero();
                p.ViewCount = (uint?)el.ViewNum.NullIfZero();
                p.ShareCount = (uint?)el.ShareNum.NullIfZero();
                // when the thread is livepost, the agree field will not exists
                p.AgreeCount = (int?)el.Agree?.AgreeNum.NullIfZero() ?? el.AgreeNum;
                p.DisagreeCount = (int?)el.Agree?.DisagreeNum.NullIfZero();
                p.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(el.Location);
                p.Zan = Helper.SerializedProtoBufOrNullIfEmpty(el.Zan);
                return p;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = p;
                e.Data["raw"] = el;
                throw new("Thread parse error.", e);
            }
        }
    }
}
