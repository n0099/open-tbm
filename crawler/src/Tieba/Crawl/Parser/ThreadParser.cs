namespace tbm.Crawler
{
    public class ThreadParser : IParser<ThreadPost, Thread>
    {
        public void ParsePosts(CrawlRequestFlag requestFlag, IEnumerable<Thread> inPosts,
            ConcurrentDictionary<ulong, ThreadPost> outPosts, List<User> outUsers)
        {
            if (requestFlag == CrawlRequestFlag.Thread602ClientVersion)
            {
                var posts = outPosts.Values;
                Thread? GetInPostsByTid(IPost p) => inPosts.FirstOrDefault(p2 => (ulong)p2.Tid == p.Tid);
                posts.Where(p => p.LatestReplierUid == null)
                    .ForEach(p => p.LatestReplierUid = GetInPostsByTid(p)?.LastReplyer.Uid);
                posts.Where(p => p.StickyType != null)
                    .ForEach(p => p.AuthorManagerType = GetInPostsByTid(p)?.Author.BawuType);
                posts.Where(p => p.Location != null)
                    .ForEach(p => p.Location = Helper.SerializedProtoBufOrNullIfEmpty(GetInPostsByTid(p)?.Location));
            }
            else
            {
                inPosts.Select(el => (ThreadPost)el).ForEach(i => outPosts[i.Tid] = i);
            }
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
                p.DisagreeNum = (int)el.Agree.DisagreeNum;
                p.Location = Helper.SerializedProtoBufOrNullIfEmpty(el.Location);
                p.ZanInfo = Helper.SerializedProtoBufOrNullIfEmpty(el.Zan);
                return p;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = p;
                e.Data["raw"] = el;
                throw new Exception("Thread parse error", e);
            }
        }
    }
}
