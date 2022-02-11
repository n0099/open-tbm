using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;
using Autofac;
using Autofac.Features.Indexed;
using Microsoft.Extensions.Logging;
using static System.Text.Json.JsonElement;
using Page = System.UInt32;
using Fid = System.UInt32;
using Tid = System.UInt64;
using Pid = System.UInt64;
using Uid = System.Int64;
using Time = System.UInt32;

namespace tbm.Crawler
{
    public sealed class ThreadCrawler : BaseCrawler<ThreadPost>
    {
        protected override CrawlerLocks CrawlerLocks { get; init; }
        private readonly string _forumName;

        public delegate ThreadCrawler New(Fid fid, string forumName);

        public ThreadCrawler(
            ILogger<ThreadCrawler> logger,
            ClientRequester requester,
            ClientRequesterTcs requesterTcs,
            IIndex<string, CrawlerLocks.New> locks,
            UserParser userParser,
            Fid fid,
            string forumName
        ) : base(logger, requester, requesterTcs, userParser, fid)
        {
            _forumName = forumName;
            CrawlerLocks = locks["thread"]("thread");
        }

        protected override Exception FillExceptionData(Exception e)
        {
            e.Data["forumName"] = _forumName;
            return e;
        }

        protected override async Task<JsonElement> CrawlSinglePage(Page page) =>
            await RequestJson("http://c.tieba.baidu.com/c/f/frs/page", new Dictionary<string, string>
            {
                {"kw", _forumName},
                {"pn", page.ToString()},
                {"rn", "50"}
            });

        protected override ArrayEnumerator GetValidPosts(JsonElement json)
        {
            ValidateOtherErrorCode(json);
            return EnsureNonEmptyPostList(json, "thread_list",
                "Forum threads list is empty, forum might doesn't existed");
        }

        protected override void ParsePosts(ArrayEnumerator posts)
        {
            List<JsonElement> users = new();
            var newPosts = posts.Select(p =>
            {
                var author = p.GetProperty("author");
                users.Add(author);
                return new ThreadPost
                {
                    Tid = Tid.Parse(p.GetStrProp("tid")),
                    FirstPid = Pid.Parse(p.GetStrProp("first_post_id")),
                    ThreadType = ulong.Parse(p.GetStrProp("thread_types")),
                    StickyType = p.GetStrProp("is_membertop") == "1"
                        ? "membertop"
                        : p.TryGetProperty("is_top", out var isTop)
                            ? isTop.GetString() == "0" ? null : "top"
                            : "top", // in 6.0.2 client version, if there's a vip sticky thread and three normal sticky threads, the fourth (oldest) thread won't have is_top field
                    IsGood = p.GetStrProp("is_good") == "1",
                    TopicType = p.TryGetProperty("is_livepost", out var isLivePost) ? isLivePost.GetString() : null,
                    Title = p.GetStrProp("title"),
                    AuthorUid = Uid.Parse(author.GetStrProp("id")),
                    AuthorManagerType = author.TryGetProperty("bawu_type", out var bawuType)
                        ? bawuType.GetString().NullIfWhiteSpace()
                        : null, // topic thread won't have this
                    PostTime = p.TryGetProperty("create_time", out var createTime)
                        ? Time.Parse(createTime.GetString() ?? "")
                        : null, // topic thread won't have this
                    LatestReplyTime = Time.Parse(p.GetStrProp("last_time_int")),
                    LatestReplierUid = Uid.Parse(p.GetProperty("last_replyer").GetStrProp("id")), // topic thread won't have this
                    ReplyNum = uint.Parse(p.GetStrProp("reply_num")),
                    ViewNum = uint.Parse(p.GetStrProp("view_num")),
                    ShareNum = p.TryGetProperty("share_num", out var shareNum)
                        ? uint.Parse(shareNum.GetString() ?? "")
                        : null, // topic thread won't have this
                    AgreeNum = uint.Parse(p.GetStrProp("agree_num")),
                    DisagreeNum = uint.Parse(p.GetStrProp("disagree_num")),
                    Location = RawJsonOrNullWhenEmpty(p.GetProperty("location")),
                    ZanInfo = RawJsonOrNullWhenEmpty(p.GetProperty("zan"))
                };
            });
            newPosts.ForEach(i => Posts[i.Tid] = i);
            Users.ParseUsers(users);
        }

        public override void SavePosts()
        {
            using var scope = Program.Autofac.BeginLifetimeScope();
            var db = scope.Resolve<TbmDbContext.New>()(Fid);
            var existing = (from thread in db.Threads
                where Posts.Keys.Any(tid => tid == thread.Tid)
                select thread).ToDictionary(i => i.Tid);
            var groupedPosts = Posts.Values.GroupBy(t => existing.ContainsKey(t.Tid)).ToList();
            IEnumerable<ThreadPost> GetNewOrExistedPosts(bool isExisted) =>
                groupedPosts.SingleOrDefault(i => i.Key == isExisted)?.ToList() ?? new List<ThreadPost>();

            var threadProps = typeof(ThreadPost).GetProperties()
                .Where(p => p.Name is not (nameof(IPost.CreatedAt) or nameof(IPost.UpdatedAt))).ToList();
            var threadRevisionProps = typeof(ThreadRevision).GetProperties();
            var nowTimestamp = (uint)DateTimeOffset.Now.ToUnixTimeSeconds();
            foreach (var newThread in GetNewOrExistedPosts(true))
            {
                ThreadRevision? revision = null;
                var oldThread = existing[newThread.Tid];
                foreach (var p in threadProps)
                {
                    var newValue = p.GetValue(newThread);
                    var oldValue = p.GetValue(oldThread);
                    if (oldValue != null && ThreadPost.JsonTypeFields.Contains(p.Name))
                    { // serialize the value of json type fields which read from db
                      // for further compare with newValue which have been re-serialized in base.RawJsonOrNullWhenEmpty()
                        using var json = JsonDocument.Parse((string)oldValue);
                        oldValue = JsonSerializer.Serialize(json);
                    }

                    if (Equals(oldValue, newValue)) continue;
                    // ef core will track changes on oldThread via reflection
                    p.SetValue(oldThread, newValue);

                    var revisionProp = threadRevisionProps.FirstOrDefault(p2 => p2.Name == p.Name);
                    if (revisionProp == null)
                        Logger.LogWarning("updating field {} is not existed in revision table, " +
                                          "newValue={}, oldValue={}, newThread={}, oldThread={}",
                            p.Name, newValue, oldValue, JsonSerializer.Serialize(newThread), JsonSerializer.Serialize(oldThread));
                    else
                    {
                        revision ??= new ThreadRevision {Time = nowTimestamp, Tid = newThread.Tid};
                        revisionProp.SetValue(revision, newValue);
                    }
                }

                if (revision != null) db.Add(revision);
            }
            db.AddRange(GetNewOrExistedPosts(false));

            // prevent update with default null value on fields which will be later set by ReplyCrawler
            db.Set<ThreadPost>().ForEach(post => db.Entry(post).Properties.Where(p => p.Metadata.Name
                    is nameof(ThreadLateSaveInfo.AntiSpamInfo) or nameof(ThreadLateSaveInfo.AuthorPhoneType))
                .ForEach(p => p.IsModified = false));
            db.SaveChanges();
        }
    }
}
