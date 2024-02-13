namespace tbm.Crawler.Tieba.Crawl.Parser;

public class ThreadParser : BaseParser<ThreadPost, Thread>
{
    protected override PostId PostIdSelector(ThreadPost post) => post.Tid;

    protected override bool ShouldSkipParse(CrawlRequestFlag requestFlag) =>
        requestFlag == CrawlRequestFlag.ThreadClientVersion602;

    protected override IEnumerable<ThreadPost> ParsePostsInternal
        (IList<Thread> inPosts, List<TbClient.User?> outUsers) => inPosts.Select(Convert);

    protected override ThreadPost Convert(Thread inPost)
    {
        var o = new ThreadPost();
        try
        {
            o.Tid = (Tid)inPost.Tid;
            o.FirstReplyExcerpt = inPost.Abstract;
            o.ThreadType = (ulong)inPost.ThreadTypes;
#pragma warning disable S3358 // Ternary operators should not be nested
            o.StickyType = inPost.IsMembertop == 1 ? "membertop" : inPost.IsTop == 0 ? null : "top";
#pragma warning restore S3358 // Ternary operators should not be nested
            o.IsGood = (byte?)inPost.IsGood.NullIfZero();
            o.TopicType = inPost.LivePostType.NullIfEmpty();
            o.Title = inPost.Title; // might be write back by ReplyCrawlFacade.SaveParentThreadTitle()
            o.AuthorUid = inPost.AuthorId;
            o.PostedAt = (uint)inPost.CreateTime;
            o.LatestReplyPostedAt = (uint)inPost.LastTimeInt;
            o.ReplyCount = inPost.ReplyNum < 0 ? 0 : (uint?)inPost.ReplyNum.NullIfZero(); // rarely respond with -1
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
            throw new InvalidDataException("Thread parse error.", e);
        }
    }
}
