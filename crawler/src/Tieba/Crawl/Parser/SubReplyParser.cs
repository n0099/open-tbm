using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using TbClient.Post;
using tbm.Crawler;

namespace tbm.Crawler
{
    public class SubReplyParser : IParser<SubReplyPost, SubReply>
    {
        public void ParsePosts(CrawlRequestFlag requestFlag, IEnumerable<SubReply> inPosts,
            ConcurrentDictionary<ulong, SubReplyPost> outPosts, UserParserAndSaver userParser)
        {
            List<TbClient.User> users = new();
            inPosts.Select(el =>
            {
                users.Add(el.Author);
                return (SubReplyPost)el;
            }).ForEach(i => outPosts[i.Spid] = i);
            userParser.ParseUsers(users);
        }
    }
}

namespace TbClient.Post
{
    public partial class SubReply
    {
        public static implicit operator SubReplyPost(SubReply el)
        {
            var p = new SubReplyPost();
            try
            {
                var author = el.Author;
                // values of property tid and pid will be write back in SubReplyCrawlFacade.PostParseCallback()
                p.Spid = el.Spid;
                p.Content = IParser<SubReplyPost, SubReply>.RawJsonOrNullWhenEmpty(JsonSerializer.Serialize(el.Content));
                p.AuthorUid = author.Id;
                p.AuthorManagerType = author.BawuType.NullIfWhiteSpace(); // will be null if he's not a moderator
                p.AuthorExpGrade = (ushort)author.LevelId;
                p.PostTime = el.Time;
                return p;
            }
            catch (Exception e)
            {
                e.Data["rawJson"] = JsonSerializer.Serialize(el);
                throw new Exception("Sub reply parse error", e);
            }
        }
    }
}
