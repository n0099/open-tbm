using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using TbClient;
using TbClient.Post;
using TbClient.Wrapper;
using tbm.Crawler;
using Tid = System.UInt64;

namespace tbm.Crawler
{
    public class ReplyParser : IParser<ReplyPost, Reply>
    {
        public List<User>? ParsePosts(CrawlRequestFlag requestFlag,
            IEnumerable<Reply> inPosts, ConcurrentDictionary<ulong, ReplyPost> outPosts)
        {
            inPosts.Select(el => (ReplyPost)el).ForEach(i => outPosts[i.Pid] = i);
            return null;
        }
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
                p.Tid = (Tid)el.Tid;
                p.Pid = el.Pid;
                p.Floor = el.Floor;
                p.Content = CommonInParser.SerializedProtoBufWrapperOrNullIfEmptyValues(() => new PostContentWrapper {Value = {el.Content}});
                p.AuthorUid = el.AuthorId;
                // values of property AuthorManagerType and AuthorExpGrade will be write back in ReplyCrawlFacade.PostParseCallback()
                p.SubReplyNum = (int)el.SubPostNumber;
                p.PostTime = el.Time;
                p.IsFold = (ushort)el.IsFold;
                p.AgreeNum = (int)el.Agree.AgreeNum;
                p.DisagreeNum = (int)el.Agree.DisagreeNum;
                p.Location = CommonInParser.SerializedProtoBufOrNullIfEmptyValues(el.LbsInfo);
                p.SignInfo = CommonInParser.SerializedProtoBufOrNullIfEmptyValues(el.Signature);
                p.TailInfo = CommonInParser.SerializedProtoBufOrNullIfEmptyValues(el.TailInfo);
                return p;
            }
            catch (Exception e)
            {
                e.Data["rawJson"] = JsonSerializer.Serialize(el);
                throw new Exception("Reply parse error", e);
            }
        }
    }
}
