using System.Text.RegularExpressions;

namespace tbm.Crawler.Tieba.Crawl.Parser
{
    public class ReplyParser : BaseParser<ReplyPost, Reply>
    {
        private static readonly Regex ImgUrlExtractingRegex = new(@"^https?://(tiebapic|imgsrc)\.baidu\.com/forum/pic/item/(?<hash>.*?)\.jpg(\?.*)*$", RegexOptions.Compiled, TimeSpan.FromSeconds(1));
        protected override PostId PostIdSelector(ReplyPost post) => post.Pid;

        protected override IEnumerable<ReplyPost> ParsePostsInternal(IEnumerable<Reply> inPosts, List<User> outUsers) =>
            inPosts.Select(r =>
            {
                outUsers.Add(r.Author);
                return Convert(r);
            });

        protected override ReplyPost Convert(Reply inPost)
        {
            var o = new ReplyPost();
            try
            {
                o.Pid = inPost.Pid;
                o.Floor = inPost.Floor;
                inPost.Content.Where(c => c.Type == 3).ForEach(c =>
                { // set with protoBuf default values to remove these image related fields that has similar value by reference
                    c.BigCdnSrc = "";
                    c.CdnSrc = "";
                    c.CdnSrcActive = "";
                    c.ShowOriginalBtn = 0;
                    c.IsLongPic = 0;
                    // only remains the image unique identity at the end of url as "filename", dropping domain, path and extension from url
                    if (ImgUrlExtractingRegex.Match(c.OriginSrc)
                            .Groups["hash"] is {Success: true} hash) c.OriginSrc = hash.Value;
                });
                o.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(
                    () => new PostContentWrapper {Value = {inPost.Content}});
                o.AuthorUid = inPost.AuthorId;
                // values of tid, AuthorManagerType and AuthorExpGrade will be write back in ReplyCrawlFacade.PostParseHook()
                o.SubReplyCount = inPost.SubPostNumber.NullIfZero();
                o.PostTime = inPost.Time;
                o.IsFold = (ushort?)inPost.IsFold.NullIfZero();
                o.AgreeCount = (int?)inPost.Agree.AgreeNum.NullIfZero();
                o.DisagreeCount = (int?)inPost.Agree.DisagreeNum.NullIfZero();
                o.Geolocation = Helper.SerializedProtoBufOrNullIfEmpty(inPost.LbsInfo);
                o.SignatureId = (uint?)inPost.Signature?.SignatureId;
                o.Signature = Helper.SerializedProtoBufOrNullIfEmpty(inPost.Signature);
                return o;
            }
            catch (Exception e)
            {
                e.Data["parsed"] = o;
                e.Data["raw"] = inPost;
                throw new("Reply parse error.", e);
            }
        }
    }
}
