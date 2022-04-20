namespace tbm.Crawler
{
    public class SubReplyParser : IParser<SubReplyPost, SubReply>
    {
        public List<User> ParsePosts(CrawlRequestFlag requestFlag,
            IEnumerable<SubReply> inPosts, ConcurrentDictionary<ulong, SubReplyPost> outPosts)
        {
            List<User> users = new();
            inPosts.Select(el =>
            {
                users.Add(el.Author);
                return (SubReplyPost)el;
            }).ForEach(i => outPosts[i.Spid] = i);
            return users;
        }
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
                p.Content = CommonInParsers.SerializedProtoBufWrapperOrNullIfEmpty(() => new PostContentWrapper {Value = {el.Content}});
                p.AuthorUid = author.Uid;
                p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                p.AuthorExpGrade = (ushort)author.LevelId;
                p.PostTime = el.Time;
                return p;
            }
            catch (Exception e)
            {
                e.Data["raw"] = el;
                throw new Exception("Sub reply parse error", e);
            }
        }
    }
}
