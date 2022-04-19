using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using TbClient;
using TbClient.Post;
using tbm.Crawler;
using Tid = System.UInt64;
using Pid = System.UInt64;

namespace tbm.Crawler
{
    public class ThreadParser : IParser<ThreadPost, Thread>
    {
        public List<User>? ParsePosts(CrawlRequestFlag requestFlag,
            IEnumerable<Thread> inPosts, ConcurrentDictionary<ulong, ThreadPost> outPosts)
        {
            if (requestFlag == CrawlRequestFlag.Thread602ClientVersion)
            {
                var posts = outPosts.Values;
                Thread? GetInPostsByTid(IPost p) => inPosts.FirstOrDefault(p2 => (ulong)p2.Tid == p.Tid);
                posts.Where(p => p.LatestReplierUid == null)
                    .ForEach(p => p.LatestReplierUid = GetInPostsByTid(p)?.LastReplyer.Uid);
                posts.Where(p => p.StickyType != null)
                    .ForEach(p => p.AuthorManagerType = GetInPostsByTid(p)?.Author.BawuType);
                posts.Where(p => p.Location != null)
                    .ForEach(p => p.Location = CommonInParser.SerializedProtoBufOrNullIfEmptyValues(GetInPostsByTid(p)?.Location));
            }
            else
            {
                inPosts.Select(el => (ThreadPost)el).ForEach(i => outPosts[i.Tid] = i);
            }

            return null;
        }
    }
}

namespace TbClient.Post
{
    public partial class Thread
    {
        public static implicit operator ThreadPost(Thread el)
        {
            var p = new ThreadPost();
            try
            {
                p.Tid = (Tid)el.Tid;
                p.FirstPid = (Pid)el.FirstPostId;
                p.ThreadType = (ulong)el.ThreadTypes;
                p.StickyType = el.IsMembertop == 1 ? "membertop" : el.IsTop == 0 ? null : "top";
                p.IsGood = el.IsGood == 1;
                p.TopicType = el.LivePostType.NullIfWhiteSpace();
                p.Title = el.Title;
                p.AuthorUid = el.AuthorId;
                p.AuthorManagerType = el.Author.BawuType.NullIfWhiteSpace();
                p.PostTime = (uint)el.CreateTime;
                p.LatestReplyTime = (uint)el.LastTimeInt;
                p.ReplyNum = (uint)el.ReplyNum;
                p.ViewNum = (uint)el.ViewNum;
                p.ShareNum = (uint)el.ShareNum;
                p.AgreeNum = el.AgreeNum;
                p.DisagreeNum = (int)el.Agree.DisagreeNum;
                p.Location = CommonInParser.SerializedProtoBufOrNullIfEmptyValues(el.Location);
                p.ZanInfo = CommonInParser.SerializedProtoBufOrNullIfEmptyValues(el.Zan);
                return p;
            }
            catch (Exception e)
            {
                e.Data["rawJson"] = JsonSerializer.Serialize(el);
                throw new Exception("Thread parse error", e);
            }
        }
    }
}
