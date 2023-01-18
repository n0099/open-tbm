namespace tbm.Crawler.Tieba.Crawl.Parser;

public class ThreadParser : BaseParser<ThreadPost, Thread>
{
    protected override PostId PostIdSelector(ThreadPost post) => post.Tid;

    protected override bool ShouldSkipParse(CrawlRequestFlag requestFlag) =>
        requestFlag == CrawlRequestFlag.ThreadClientVersion602;

    protected override IEnumerable<ThreadPost> ParsePostsInternal(IList<Thread> inPosts, List<User?> outUsers) => inPosts.Select(Convert);

    protected override ThreadPost Convert(Thread inPost)
    {
        var o = new ThreadPost();
        try
        {
            o.Tid = (Tid)inPost.Tid;
            // FirstReplyPid will be write back in this.ShouldSkipParse()
            o.FirstReplyExcerpt = inPost.Abstract;
            o.ThreadType = (ulong)inPost.ThreadTypes;
            o.StickyType = inPost.IsMembertop == 1 ? "membertop" : inPost.IsTop == 0 ? null : "top";
            o.IsGood = (ushort?)inPost.IsGood.NullIfZero();
            o.TopicType = inPost.LivePostType.NullIfWhiteSpace();
            o.Title = inPost.Title; // might be write back by ReplyCrawlFacade.SaveParentThreadTitle()
            o.AuthorUid = inPost.AuthorId;
            o.PostedAt = (uint)inPost.CreateTime;
            o.LatestReplyPostedAt = (uint)inPost.LastTimeInt;
            // LastReplyer will be null when LivePostType != "", but LastTimeInt will have expected timestamp value
            o.LatestReplierUid = inPost.LastReplyer?.Uid;
            o.ReplyCount = (uint?)inPost.ReplyNum.NullIfZero();
            o.ViewCount = (uint?)inPost.ViewNum.NullIfZero();
            o.ShareCount = (uint?)inPost.ShareNum.NullIfZero();
            // when the thread is livepost or Thread.AgreeNum == 0, the agree field will not exists
            o.AgreeCount = (int?)inPost.Agree?.AgreeNum.NullIfZero() ?? inPost.AgreeNum.NullIfZero();
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
