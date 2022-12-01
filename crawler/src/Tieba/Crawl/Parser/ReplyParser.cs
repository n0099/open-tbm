using System.Text.RegularExpressions;

namespace tbm.Crawler
{
    public class ReplyParser : BaseParser<ReplyPost, Reply>
    {
        private static readonly Regex ImgUrlExtractingRegex = new(@"^https?://(tiebapic|imgsrc)\.baidu\.com/forum/pic/item/(?<hash>.*?)\.jpg(\?.*)*$", RegexOptions.Compiled, TimeSpan.FromSeconds(1));
        protected override PostId PostIdSelector(ReplyPost post) => post.Pid;

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
                    // only remains the image unique identity at the end of url as "filename", dropping domain, path and extension from url
                    if (ImgUrlExtractingRegex.Match(c.OriginSrc).Groups["hash"] is {Success: true} hash) c.OriginSrc = hash.Value;
                });
                p.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(() => new PostContentWrapper {Value = {el.Content}});
                p.AuthorUid = el.AuthorId;
                // values of tid, AuthorManagerType and AuthorExpGrade will be write back in ReplyCrawlFacade.PostParseHook()
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
