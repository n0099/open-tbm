namespace tbm.Crawler
{
    public class SubReplyParser : BaseParser<SubReplyPost, SubReply>
    {
        protected override (IEnumerable<SubReplyPost> parsed, Func<SubReplyPost, PostId> postIdSelector)? ParsePostsInternal(
            CrawlRequestFlag requestFlag, IEnumerable<SubReply> inPosts,
            ConcurrentDictionary<PostId, SubReplyPost> outPosts, List<User> outUsers) =>
            (inPosts.Select(el =>
            {
                outUsers.Add(el.Author);
                return (SubReplyPost)el;
            }), p => p.Spid);
    }
}

namespace TbClient.Post
{
    public partial class SubReply
    {
        public static implicit operator SubReplyPost(SubReply el)
        {
            var p = new SubReplyPost();
            try
            {
                var author = el.Author;
                // values of property tid and pid will be write back in SubReplyCrawlFacade.PostParseCallback()
                p.Spid = el.Spid;
                p.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new PostContentWrapper {Value = {el.Content}});
                p.AuthorUid = author.Uid;
                p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                p.AuthorExpGrade = (ushort)author.LevelId;
                p.PostTime = el.Time;
                return p;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = p;
                e.Data["raw"] = el;
                throw new Exception("Sub reply parse error", e);
            }
        }
    }
}
