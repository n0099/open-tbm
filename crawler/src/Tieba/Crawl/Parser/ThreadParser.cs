namespace tbm.Crawler
{
    public class ThreadParser : BaseParser<ThreadPost, Thread>
    {
        protected override (IEnumerable<ThreadPost> parsed, Func<ThreadPost, PostId> postIdSelector)? ParsePostsInternal(
            CrawlRequestFlag requestFlag, IEnumerable<Thread> inPosts,
            ConcurrentDictionary<PostId, ThreadPost> outPosts, List<User> outUsers)
        {
            if (requestFlag == CrawlRequestFlag.Thread602ClientVersion)
            {
                var posts = outPosts.Values;
                Thread? GetInPostsByTid(IPost t) => inPosts.FirstOrDefault(t2 => (Tid)t2.Tid == t.Tid);
                posts.Where(t => t.LatestReplierUid == null)
                    // when the thread is livepost, the last replier field will not exists in the response of tieba client 6.0.2
                    .ForEach(t => t.LatestReplierUid = GetInPostsByTid(t)?.LastReplyer?.Uid);
                posts.Where(t => t.StickyType != null)
                    // using value from 6.0.2 response to fix that in the 12.x response author uid will be 0 and all the other fields is filled with default value
                    .ForEach(t => t.AuthorManagerType = GetInPostsByTid(t)?.Author.BawuType);
                posts.Where(t => t.Location != null) // replace with more detailed location.name in the 6.0.2 response
                    .ForEach(t => t.Location = Helper.SerializedProtoBufOrNullIfEmpty(GetInPostsByTid(t)?.Location));
                return null;
            }

            return (inPosts.Select(el => (ThreadPost)el), p => p.Tid);
        }
    }
}

namespace TbClient.Post
{
    public partial class Thread
    {
        public static implicit operator ThreadPost(Thread el)
        {
            var p = new ThreadPost();
            try
            {
                p.Tid = (Tid)el.Tid;
                p.FirstPid = (Pid)el.FirstPostId;
                p.ThreadType = (ulong)el.ThreadTypes;
                p.StickyType = el.IsMembertop == 1 ? "membertop" : el.IsTop == 0 ? null : "top";
                p.IsGood = el.IsGood == 1;
                p.TopicType = el.LivePostType.NullIfWhiteSpace();
                p.Title = el.Title;
                p.AuthorUid = el.AuthorId;
                p.AuthorManagerType = el.Author.BawuType.NullIfWhiteSpace();
                p.PostTime = (uint)el.CreateTime;
                p.LatestReplyTime = (uint)el.LastTimeInt;
                p.ReplyNum = (uint)el.ReplyNum;
                p.ViewNum = (uint)el.ViewNum;
                p.ShareNum = (uint)el.ShareNum;
                p.AgreeNum = el.AgreeNum;
                // when the thread is livepost, the agree field will not exists
                p.DisagreeNum = (int?)el?.Agree?.DisagreeNum ?? 0;
                p.Location = Helper.SerializedProtoBufOrNullIfEmpty(el?.Location);
                p.ZanInfo = Helper.SerializedProtoBufOrNullIfEmpty(el?.Zan);
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
