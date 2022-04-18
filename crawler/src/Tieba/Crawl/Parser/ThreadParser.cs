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
                Thread? GetInPostsByTid(IPost p) => inPosts.FirstOrDefault(p2 => (ulong)p2.Tid == p.Tid);
                outPosts.Values.Where(p => p.LatestReplierUid == null)
                    .ForEach(p => p.LatestReplierUid = GetInPostsByTid(p)?.LastReplyer.Id);
                outPosts.Values.Where(p => p.StickyType != null)
                    .ForEach(p => p.AuthorManagerType = GetInPostsByTid(p)?.Author.BawuType);
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
                p.ThreadType = (ulong)el.ThreadType;
                p.StickyType = el.IsMembertop == 1 ? "membertop" : el.IsTop == 0 ? null : "top";
                p.IsGood = el.IsGood == 1;
                p.TopicType = el.IsLivepost.ToString();
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
                p.Location = IParser<ThreadPost, Thread>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Location));
                p.ZanInfo = IParser<ThreadPost, Thread>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Zan));
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
