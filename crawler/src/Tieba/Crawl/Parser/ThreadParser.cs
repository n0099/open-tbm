namespace tbm.Crawler
{
    public class ThreadParser : BaseParser<ThreadPost, Thread>
    {
        protected override ulong PostIdSelector(ThreadPost post) => post.Tid;

        protected override bool TrySkipParse(CrawlRequestFlag requestFlag, IEnumerable<Thread> inPosts, ConcurrentDictionary<ulong, ThreadPost> outPosts)
        {
            if (requestFlag != CrawlRequestFlag.Thread602ClientVersion) return false;
            var posts = outPosts.Values;
            Thread? GetInPostsByTid(IPost t) => inPosts.FirstOrDefault(t2 => (Tid)t2.Tid == t.Tid);
            posts.Where(t => t.LatestReplierUid == null)
                // when the thread is livepost, the last replier field will not exists in the response of tieba client 6.0.2
                .ForEach(t => t.LatestReplierUid = GetInPostsByTid(t)?.LastReplyer?.Uid);
            posts.Where(t => t.StickyType != null)
                // using value from 6.0.2 response to fix that in the 12.x response author uid will be 0 and all the other fields is filled with default value
                .ForEach(t => t.AuthorManagerType = GetInPostsByTid(t)?.Author.BawuType);
            posts.Where(t => t.Geolocation != null) // replace with more detailed location.name in the 6.0.2 response
                .ForEach(t => t.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(GetInPostsByTid(t)?.Location));
            return true;
        }

        protected override (ThreadPost First, ThreadPost Last) GetFirstAndLastOfParsed(List<ThreadPost> parsed)
        {
            parsed = parsed.Where(t => t.StickyType == null).ToList();
            return (parsed.First(), parsed.Last());
        }

        protected override IEnumerable<ThreadPost> ParsePostsInternal(IEnumerable<Thread> inPosts, List<User> outUsers) => inPosts.Select(Convert);

        protected override ThreadPost Convert(Thread el)
        {
            var p = new ThreadPost();
            try
            {
                p.Tid = (Tid)el.Tid;
                p.FirstPid = (Pid)el.FirstPostId;
                p.ThreadType = (ulong)el.ThreadTypes;
                p.StickyType = el.IsMembertop == 1 ? "membertop" : el.IsTop == 0 ? null : "top";
                p.IsGood = (ushort?)el.IsGood.NullIfZero();
                p.TopicType = el.LivePostType.NullIfWhiteSpace();
                p.Title = el.Title;
                p.AuthorUid = el.AuthorId.NullIfZero() ?? el.Author.Uid;
                p.PostTime = (uint)el.CreateTime;
                p.LatestReplyTime = (uint)el.LastTimeInt;
                p.ReplyNum = (uint?)el.ReplyNum.NullIfZero();
                p.ViewNum = (uint?)el.ViewNum.NullIfZero();
                p.ShareNum = (uint?)el.ShareNum.NullIfZero();
                // when the thread is livepost, the agree field will not exists
                p.AgreeNum = (int?)el.Agree?.AgreeNum.NullIfZero() ?? el.AgreeNum;
                p.DisagreeNum = (int?)el.Agree?.DisagreeNum.NullIfZero();
                p.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(el.Location);
                p.ZanInfo = Helper.SerializedProtoBufOrNullIfEmpty(el.Zan);
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
