using System.Collections.Concurrent;
using System.Linq;
using LinqKit;
using Microsoft.Extensions.Logging;
using Fid = System.UInt32;

namespace tbm.Crawler
{
    public class ThreadSaver : BaseSaver<ThreadPost>
    {
        private readonly Fid _fid;

        public ThreadSaver(ILogger<ThreadSaver> logger, ConcurrentDictionary<ulong, ThreadPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override void SavePosts(TbmDbContext db)
        {
            SavePosts(db,
                PredicateBuilder.New<ThreadPost>(p => Posts.Keys.Any(id => id == p.Tid)),
                PredicateBuilder.New<PostIndex>(i => i.Type == "thread" && Posts.Keys.Any(id => id == i.Tid)),
                p => p.Tid,
                i => i.Tid,
                p => new PostIndex { Type = "thread", Fid = _fid, Tid = p.Tid, PostTime = p.PostTime },
                (now, p) => new ThreadRevision { Time = now, Tid = p.Tid });
            foreach (var post in db.Set<ThreadPost>().Local)
            { // prevent update with default null value on fields which will be later set by ReplyCrawler
                db.Entry(post).Properties
                    .Where(p => p.Metadata.Name is nameof(ThreadLateSaveInfo.AntiSpamInfo) or nameof(ThreadLateSaveInfo.AuthorPhoneType))
                    .ForEach(p => p.IsModified = false);
            }
        }
    }
}
