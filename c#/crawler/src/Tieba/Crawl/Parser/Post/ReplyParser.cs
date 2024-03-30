namespace tbm.Crawler.Tieba.Crawl.Parser;

public partial class ReplyParser(ILogger<ReplyParser> logger)
    : BasePostParser<ReplyPost, Reply>
{
    // length with 24 char is only appeared in legacy replies
    [GeneratedRegex("^(?:[0-9a-f]{40}|[0-9a-f]{24})$", RegexOptions.Compiled, matchTimeoutMilliseconds: 100)]
    public static partial Regex ValidateContentImageFilenameRegex();

    protected override PostId PostIdSelector(ReplyPost post) => post.Pid;

    protected override IEnumerable<ReplyPost> ParseInternal
        (IReadOnlyList<Reply> inPosts, IList<TbClient.User?> outUsers) => inPosts.Select(Convert);

    protected override ReplyPost Convert(Reply inPost)
    {
        var o = new ReplyPost {OriginalContents = inPost.Content};
        try
        {
            o.Pid = inPost.Pid;
            o.Floor = inPost.Floor;
            foreach (var c in inPost.Content.Where(c => c.Type == 3))
            { // reset with the protoBuf default values to remove these image related fields that have similar values
                if (!Uri.TryCreate(c.OriginSrc, UriKind.Absolute, out var uri)) continue;
                c.Src = "";
                c.CdnSrc = "";
                c.CdnSrcActive = "";
                c.BigCdnSrc = "";
                c.ShowOriginalBtn = 0;
                c.IsLongPic = 0;
                var urlFilename = Path.GetFileNameWithoutExtension(uri.AbsolutePath);

                // only remains the image unique identity at the end of url as "filename"
                // drops domain, path and file extension from url
                if (uri.Host is "tiebapic.baidu.com" or "imgsrc.baidu.com"
                        or "hiphotos.baidu.com" // http://hiphotos.baidu.com/bhitozratlo/pic/item/f1671ef3678e7608352accad.jpg
                    && ValidateContentImageFilenameRegex().IsMatch(urlFilename))
                {
                    c.OriginSrc = urlFilename;
                }
                else if (uri.Host != "tb2.bdstatic.com") // http://tb2.bdstatic.com/tb/cms/commonsub/editor/images/qw_cat_small/qw_cat_0001.gif
                {
                    logger.LogInformation("Detected an image in the content of reply with pid {} references to {}"
                                          + " instead of common domains of tieba image hosting service, content={}",
                        o.Pid, c.OriginSrc, Helper.UnescapedJsonSerialize(c));
                }
            }
            o.Content = Helper.SerializedProtoBufWrapperOrNullIfEmpty(inPost.Content,
                () => Helper.WrapPostContent(inPost.Content));

            // AuthorId rarely respond with 0, Author should always be null with no guarantee
            o.AuthorUid = inPost.AuthorId.NullIfZero() ?? inPost.Author?.Uid ?? 0;

            // value of AuthorExpGrade will be written back in ReplyCrawlFacade.FillAuthorInfoBackToReply()
            o.SubReplyCount = inPost.SubPostNumber.NullIfZero();
            o.PostedAt = inPost.Time;
            o.IsFold = (byte?)inPost.IsFold.NullIfZero();
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
            throw new InvalidDataException("Reply parse error.", e);
        }
    }
}
