namespace tbm.Crawler.Tieba.Crawl.Parser
{
    public class SubReplyParser : BaseParser<SubReplyPost, SubReply>
    {
        protected override PostId PostIdSelector(SubReplyPost post) => post.Spid;

        protected override IEnumerable<SubReplyPost> ParsePostsInternal(IEnumerable<SubReply> inPosts, List<User> outUsers) =>
            inPosts.Select(sr =>
            {
                outUsers.Add(sr.Author);
                return Convert(sr);
            });

        protected override SubReplyPost Convert(SubReply inPost)
        {
            var o = new SubReplyPost();
            try
            {
                var author = inPost.Author;
                // values of tid and pid will be write back in SubReplyCrawlFacade.PostParseHook()
                o.Spid = inPost.Spid;
                o.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(
                    () => new PostContentWrapper {Value = {inPost.Content}});
                o.AuthorUid = author.Uid;
                o.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                o.AuthorExpGrade = (ushort)author.LevelId;
                o.PostTime = inPost.Time;
                o.AgreeCount = (int?)inPost.Agree.AgreeNum.NullIfZero();
                o.DisagreeCount = (int?)inPost.Agree.DisagreeNum.NullIfZero();
                return o;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = o;
                e.Data["raw"] = inPost;
                throw new("Sub reply parse error.", e);
            }
        }
    }
}
