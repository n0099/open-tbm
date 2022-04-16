using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using TbClient.Post;
using tbm.Crawler;
using Tid = System.UInt64;

namespace tbm.Crawler
{
    public class ReplyParser : IParser<ReplyPost, Reply>
    {
        public void ParsePosts(CrawlRequestFlag requestFlag, IEnumerable<Reply> inPosts,
            ConcurrentDictionary<ulong, ReplyPost> outPosts, UserParserAndSaver userParser) =>
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
                p.Tid = (Tid)el.Tid;
                p.Pid = el.Pid;
                p.Floor = el.Floor;
                p.Content = IParser<ReplyPost, Reply>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Content));
                p.AuthorUid = el.AuthorId;
                p.SubReplyNum = (int)el.SubPostNumber;
                p.PostTime = el.Time;
                p.IsFold = (ushort)el.IsFold;
                p.AgreeNum = (int)el.Agree.AgreeNum;
                p.DisagreeNum = (int)el.Agree.DisagreeNum;
                p.Location = IParser<ReplyPost, Reply>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.LbsInfo));
                p.SignInfo = IParser<ReplyPost, Reply>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Signature));
                p.TailInfo = IParser<ReplyPost, Reply>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.TailInfo));
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
