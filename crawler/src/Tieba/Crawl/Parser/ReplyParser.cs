namespace tbm.Crawler
{
    public class ReplyParser : IParser<ReplyPost, Reply>
    {
        public void ParsePosts(CrawlRequestFlag requestFlag, IEnumerable<Reply> inPosts,
            ConcurrentDictionary<ulong, ReplyPost> outPosts, List<User> outUsers) =>
            inPosts.Select(el => (ReplyPost)el).ForEach(i => outPosts[i.Pid] = i);
    }
}

namespace TbClient.Post
{
    public partial class Reply
    {
        public static implicit operator ReplyPost(Reply el)
        {
            var p = new ReplyPost();
            try
            {
                p.Pid = el.Pid;
                p.Floor = el.Floor;
                p.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new PostContentWrapper {Value = {el.Content}});
                p.AuthorUid = el.AuthorId;
                // values of property tid, AuthorManagerType and AuthorExpGrade will be write back in ReplyCrawlFacade.PostParseCallback()
                p.SubReplyNum = (int)el.SubPostNumber;
                p.PostTime = el.Time;
                p.IsFold = (ushort)el.IsFold;
                p.AgreeNum = (int)el.Agree.AgreeNum;
                p.DisagreeNum = (int)el.Agree.DisagreeNum;
                p.Location = Helper.SerializedProtoBufOrNullIfEmpty(el.LbsInfo);
                p.SignInfo = Helper.SerializedProtoBufOrNullIfEmpty(el.Signature);
                p.TailInfo = Helper.SerializedProtoBufOrNullIfEmpty(el.TailInfo);
                return p;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = p;
                e.Data["raw"] = el;
                throw new Exception("Reply parse error", e);
            }
        }
    }
}
