namespace tbm.Crawler
{
    public class ReplyParser : BaseParser<ReplyPost, Reply>
    {
        protected override ulong PostIdSelector(ReplyPost post) => post.Pid;

        protected override IEnumerable<ReplyPost> ParsePostsInternal(IEnumerable<Reply> inPosts, List<User> outUsers) => inPosts.Select(Convert);

        protected override ReplyPost Convert(Reply el)
        {
            var p = new ReplyPost();
            try
            {
                p.Pid = el.Pid;
                p.Floor = el.Floor;
                el.Content.Where(c => c.Type == 3).ForEach(c =>
                { // set with protoBuf default value to remove these image related fields that has similar value by reference
                    c.BigCdnSrc = "";
                    c.CdnSrc = "";
                    c.CdnSrcActive = "";
                    c.ShowOriginalBtn = 0;
                    c.IsLongPic = 0;
                });
                p.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new PostContentWrapper {Value = {el.Content}});
                p.AuthorUid = el.AuthorId;
                // values of tid, AuthorManagerType and AuthorExpGrade will be write back in ReplyCrawlFacade.PostParseCallback()
                p.SubReplyNum = el.SubPostNumber.NullIfZero();
                p.PostTime = el.Time;
                p.IsFold = (ushort?)el.IsFold.NullIfZero();
                p.AgreeNum = (int?)el.Agree.AgreeNum.NullIfZero();
                p.DisagreeNum = (int?)el.Agree.DisagreeNum.NullIfZero();
                p.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(el.LbsInfo);
                p.SignatureId = (uint?)el.Signature?.SignatureId;
                p.Signature = Helper.SerializedProtoBufOrNullIfEmpty(el.Signature);
                return p;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = p;
                e.Data["raw"] = el;
                throw new("Reply parse error.", e);
            }
        }
    }
}
