using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using TbClient.Post;
using tbm.Crawler;
using Tid = System.UInt64;
using Pid = System.UInt64;

namespace tbm.Crawler
{
    public class ThreadParser : IParser<ThreadPost, Thread>
    {
        public void ParsePosts(CrawlRequestFlag requestFlag, IEnumerable<Thread> inPosts,
            ConcurrentDictionary<ulong, ThreadPost> outPosts, UserParserAndSaver userParser)
        {
            List<TbClient.User> users = new();
            if (requestFlag == CrawlRequestFlag.Thread602ClientVersion)
            {
                Thread? InPostsTidFilter(IPost p) => inPosts.FirstOrDefault(p2 => (ulong)p2.Tid == p.Tid);
                outPosts.Values.Where(p => p.LatestReplierUid == null)
                    .ForEach(p => p.LatestReplierUid =
                        InPostsTidFilter(p)?.LastReplyer.Id);
                outPosts.Values.Where(p => p.StickyType != null)
                    .ForEach(p =>
                    {
                        var t = InPostsTidFilter(p);
                        if (t == null) return;
                        p.AuthorUid = t.AuthorId;
                        p.AuthorManagerType = t.Author.BawuType;
                    });
            }
            else
            {
                inPosts.Select(el =>
                {
                    users.Add(el.Author);
                    return (ThreadPost)el;
                }).ForEach(i => outPosts[i.Tid] = i);
            }
            userParser.ParseUsers(users);
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
                p.AuthorUid = el.Author.Id;
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
