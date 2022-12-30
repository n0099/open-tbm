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
                    outThreads.Where(t => t.Geolocation != null) // replace with more detailed location.name in the 6.0.2 response
                        .ForEach(t => t.Geolocation =
                            Helper.SerializedProtoBufOrNullIfEmpty(GetInPostsByTid(t)?.Location));
                    return true;
                },
                CrawlRequestFlag.ThreadClientVersion8888 => () =>
                {
                    outThreads.Where(t => t.FirstReplyPid == null)
                        .ForEach(t => t.FirstReplyPid = (Pid?)GetInPostsByTid(t)?.FirstPostId);
                    return true;
                },
                _ => throw new ArgumentOutOfRangeException(
                    nameof(requestFlag), requestFlag, "Unexpected CrawlRequestFlag.")
            };
            return testRequestFlag();
        }

        protected override IEnumerable<ThreadPost> ParsePostsInternal(IEnumerable<Thread> inPosts, List<User> outUsers) =>
            inPosts.Select(t =>
            {
                outUsers.Add(t.Author);
                return Convert(t);
            });

        protected override ThreadPost Convert(Thread inPost)
        {
            var o = new ThreadPost();
            try
            {
                o.Tid = (Tid)inPost.Tid;
                o.FirstReplyExcerpt = Helper.SerializedProtoBufWrapperOrNullIfEmpty(
                    () => new ThreadAbstractWrapper {Value = {inPost.Abstract}});
                o.ThreadType = (ulong)inPost.ThreadTypes;
                o.StickyType = inPost.IsMembertop == 1 ? "membertop" : inPost.IsTop == 0 ? null : "top";
                o.IsGood = (ushort?)inPost.IsGood.NullIfZero();
                o.TopicType = inPost.LivePostType.NullIfWhiteSpace();
                o.Title = inPost.Title;
                o.AuthorUid = inPost.Author.Uid;
                o.AuthorManagerType = inPost.Author.BawuType.NullIfWhiteSpace();
                o.PostTime = (uint)inPost.CreateTime;
                o.LatestReplyTime = (uint)inPost.LastTimeInt;
                o.LatestReplierUid = inPost.LastReplyer?.Uid; // LastReplyer will be null when LivePostType != "", but LastTimeInt will have expected timestamp value
                o.ReplyCount = (uint?)inPost.ReplyNum.NullIfZero();
                o.ViewCount = (uint?)inPost.ViewNum.NullIfZero();
                o.ShareCount = (uint?)inPost.ShareNum.NullIfZero();
                // when the thread is livepost, the agree field will not exists
                o.AgreeCount = (int?)inPost.Agree?.AgreeNum.NullIfZero() ?? inPost.AgreeNum;
                o.DisagreeCount = (int?)inPost.Agree?.DisagreeNum.NullIfZero();
                o.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(inPost.Location);
                o.Zan = Helper.SerializedProtoBufOrNullIfEmpty(inPost.Zan);
                return o;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = o;
                e.Data["raw"] = inPost;
                throw new("Thread parse error.", e);
            }
        }
    }
}
