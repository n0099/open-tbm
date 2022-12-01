namespace tbm.Crawler
{
    public class SubReplyParser : BaseParser<SubReplyPost, SubReply>
    {
        protected override PostId PostIdSelector(SubReplyPost post) => post.Spid;

        protected override IEnumerable<SubReplyPost> ParsePostsInternal(IEnumerable<SubReply> inPosts, List<User> outUsers) =>
            inPosts.Select(el =>
            {
                outUsers.Add(el.Author);
                return Convert(el);
            });

        protected override SubReplyPost Convert(SubReply el)
        {
            var p = new SubReplyPost();
            try
            {
                var author = el.Author;
                // values of tid and pid will be write back in SubReplyCrawlFacade.PostParseHook()
                p.Spid = el.Spid;
                p.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new PostContentWrapper {Value = {el.Content}});
                p.AuthorUid = author.Uid;
                p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                p.AuthorExpGrade = (ushort)author.LevelId;
                p.PostTime = el.Time;
                p.AgreeNum = (int?)el.Agree.AgreeNum.NullIfZero();
                p.DisagreeNum = (int?)el.Agree.DisagreeNum.NullIfZero();
                return p;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = p;
                e.Data["raw"] = el;
                throw new("Sub reply parse error.", e);
            }
        }
    }
}
