namespace tbm.Crawler.Tieba.Crawl.Parser.Post;

public class SubReplyParser : PostParser<SubReplyPost, SubReply>
{
    protected override PostId PostIdSelector(SubReplyPost post) => post.Spid;

    protected override IEnumerable<SubReplyPost> ParseInternal
        (IReadOnlyCollection<SubReply> inPosts, ICollection<TbClient.User?> outUsers)
    {
        outUsers.AddRange(inPosts.Select(sr => sr.Author));
        return inPosts.Select(Convert);
    }

    protected override SubReplyPost Convert(SubReply inPost)
    {
        var o = new SubReplyPost
        {
            OriginalContents = inPost.Content,
            Content = new()
            {
                Spid = inPost.Spid,
                ProtoBufBytes = Helper.SerializedProtoBufWrapperOrNullIfEmpty(inPost.Content,
                    () => Helper.WrapPostContent(inPost.Content))
            }
        };
        try
        {
            var author = inPost.Author;
            o.Spid = inPost.Spid;
            o.AuthorUid = author.Uid;
            o.AuthorExpGrade = (byte)author.LevelId;
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
