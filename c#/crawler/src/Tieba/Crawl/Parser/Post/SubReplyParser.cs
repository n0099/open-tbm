namespace tbm.Crawler.Tieba.Crawl.Parser.Post;

public class SubReplyParser : PostParser<SubReplyPost.Parsed, SubReply>
{
    protected override PostId PostIdSelector(SubReplyPost.Parsed post) => post.Spid;

    protected override IEnumerable<SubReplyPost.Parsed> ParseInternal
        (IReadOnlyCollection<SubReply> inPosts, ICollection<TbClient.User?> outUsers)
    {
        outUsers.AddRange(inPosts.Select(sr => sr.Author));
        return inPosts.Select(Convert);
    }

    protected override SubReplyPost.Parsed Convert(SubReply inPost)
    {
        var o = new SubReplyPost.Parsed {ContentsProtoBuf = inPost.Content};
        try
        {
            var author = inPost.Author;
            o.Spid = inPost.Spid;
            o.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(inPost.Content, Helper.WrapPostContent);
            o.AuthorUid = author.Uid;
            o.PostedAt = inPost.Time;
            o.AgreeCount = (int?)inPost.Agree.AgreeNum.NullIfZero();
            o.DisagreeCount = (int?)inPost.Agree.DisagreeNum.NullIfZero();
            return o;
        }
        catch (Exception e)
        {
            e.Data["parsed"] = o;
            e.Data["raw"] = inPost;
            throw new InvalidDataException("Sub reply parse error.", e);
        }
    }
}
