using System.Collections.Concurrent;
using System.Linq;
using LinqKit;
using Microsoft.Extensions.Logging;
using Fid = System.UInt32;

namespace tbm.Crawler
{
    public class SubReplySaver : BaseSaver<SubReplyPost>
    {
        private readonly Fid _fid;

        public SubReplySaver(ILogger<SubReplySaver> logger, ConcurrentDictionary<ulong, SubReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override void SavePosts(TbmDbContext db) => SavePosts(db,
            PredicateBuilder.New<SubReplyPost>(p => Posts.Keys.Any(id => id == p.Spid)),
            PredicateBuilder.New<PostIndex>(i => i.Type == "reply" && Posts.Keys.Any(id => id == i.Spid)),
            p => p.Spid,
            i => i.Spid,
            p => new PostIndex {Type = "reply", Fid = _fid, Tid = p.Tid, Pid = p.Pid, Spid = p.Spid, PostTime = p.PostTime},
            (now, p) => new SubReplyRevision {Time = now, Spid = p.Spid});
    }
}
