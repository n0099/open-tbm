using Microsoft.EntityFrameworkCore.Infrastructure;
using static tbm.Crawler.Db.Revision.ThreadRevision;
using static tbm.Crawler.Db.Revision.ReplyRevision;
using static tbm.Crawler.Db.Revision.SubReplyRevision;
using static tbm.Crawler.Db.Revision.UserRevision;

namespace tbm.Crawler.Db;

public class CrawlerDbContext : TbmDbContext<CrawlerDbContext.ModelWithFidCacheKeyFactory>
{
    public class ModelWithFidCacheKeyFactory : IModelCacheKeyFactory
    { // https://stackoverflow.com/questions/51864015/entity-framework-map-model-class-to-table-at-run-time/51899590#51899590
        // https://docs.microsoft.com/en-us/ef/core/modeling/dynamic-model
        public object Create(DbContext context, bool designTime) =>
            context is CrawlerDbContext dbContext
                ? (context.GetType(), dbContext.Fid, designTime)
                : context.GetType();
    }

    public Fid Fid { get; }
    public DbSet<TiebaUser> Users => Set<TiebaUser>();
    public DbSet<TiebaImage> Images => Set<TiebaImage>();
    public DbSet<AuthorExpGradeRevision> AuthorExpGradeRevisions => Set<AuthorExpGradeRevision>();
    public DbSet<ForumModeratorRevision> ForumModeratorRevisions => Set<ForumModeratorRevision>();
    public DbSet<ThreadPost> Threads => Set<ThreadPost>();
    public DbSet<ThreadMissingFirstReply> ThreadMissingFirstReplies => Set<ThreadMissingFirstReply>();
    public DbSet<ReplyPost> Replies => Set<ReplyPost>();
    public DbSet<ReplySignature> ReplySignatures => Set<ReplySignature>();
    public DbSet<ReplyContent> ReplyContents => Set<ReplyContent>();
    public DbSet<ReplyContentImage> ReplyContentImages => Set<ReplyContentImage>();
    public DbSet<SubReplyPost> SubReplies => Set<SubReplyPost>();
    public DbSet<SubReplyContent> SubReplyContents => Set<SubReplyContent>();
    public DbSet<Forum> Forum => Set<Forum>();

    public delegate CrawlerDbContext New(Fid fid);

    public CrawlerDbContext(IConfiguration config, Fid fid) : base(config) => Fid = fid;

#pragma warning disable IDE0058 // Expression value is never used
    protected override void OnModelCreating(ModelBuilder b)
    {
        b.Entity<TiebaUser>().ToTable("tbmc_user");
        b.Entity<TiebaImage>().ToTable("tbmc_image");
        b.Entity<ThreadPost>().ToTable($"tbmc_f{Fid}_thread");
        b.Entity<ThreadMissingFirstReply>().ToTable("tbmc_thread_missingFirstReply");
        b.Entity<ReplyPost>().ToTable($"tbmc_f{Fid}_reply");
        b.Entity<ReplySignature>().ToTable("tbmc_reply_signature").HasKey(e => new {e.SignatureId, e.SignatureXxHash3});
        b.Entity<ReplyContent>().ToTable($"tbmc_f{Fid}_reply_content");
        b.Entity<ReplyContentImage>().ToTable("tbmc_reply_content_image").HasKey(e => new {e.Pid, e.ImageId});
        b.Entity<ReplyContentImage>().HasOne(e => e.Image).WithMany();
        b.Entity<SubReplyPost>().ToTable($"tbmc_f{Fid}_subReply");
        b.Entity<SubReplyContent>().ToTable($"tbmc_f{Fid}_subReply_content");

        var thread = new RevisionWithSplitting<BaseThreadRevision>.ModelBuilderHelper(b, "tbmcr_thread");
        thread.HasKey<ThreadRevision>(e => new {e.Tid, e.TakenAt});
        thread.SplittingHasKeyAndName<SplitViewCount>("viewCount", e => new {e.Tid, e.TakenAt});

        var reply = new RevisionWithSplitting<BaseReplyRevision>.ModelBuilderHelper(b, "tbmcr_reply");
        reply.HasKey<ReplyRevision>(e => new {e.Pid, e.TakenAt});
        reply.SplittingHasKeyAndName<ReplyRevision.SplitAgreeCount>("agreeCount", e => new {e.Pid, e.TakenAt});
        reply.SplittingHasKeyAndName<SplitSubReplyCount>("subReplyCount", e => new {e.Pid, e.TakenAt});
        reply.SplittingHasKeyAndName<SplitFloor>("floor", e => new {e.Pid, e.TakenAt});

        var subReply = new RevisionWithSplitting<BaseSubReplyRevision>.ModelBuilderHelper(b, "tbmcr_subReply");
        subReply.HasKey<SubReplyRevision>(e => new {e.Spid, e.TakenAt});
        subReply.SplittingHasKeyAndName<SubReplyRevision.SplitAgreeCount>("agreeCount", e => new {e.Spid, e.TakenAt});
        subReply.SplittingHasKeyAndName<SplitDisagreeCount>("disagreeCount", e => new {e.Spid, e.TakenAt});

        var user = new RevisionWithSplitting<BaseUserRevision>.ModelBuilderHelper(b, "tbmcr_user");
        user.HasKey<UserRevision>(e => new {e.Uid, e.TakenAt});
        user.SplittingHasKeyAndName<SplitIpGeolocation>("ipGeolocation", e => new {e.Uid, e.TakenAt});
        user.SplittingHasKeyAndName<SplitPortraitUpdatedAt>("portraitUpdatedAt", e => new {e.Uid, e.TakenAt});
        user.SplittingHasKeyAndName<SplitDisplayName>("displayName", e => new {e.Uid, e.TakenAt});

        b.Entity<AuthorExpGradeRevision>().ToTable("tbmcr_authorExpGrade").HasKey(e => new {e.Fid, e.Uid, e.DiscoveredAt});
        b.Entity<ForumModeratorRevision>().ToTable("tbmcr_forumModerator").HasKey(e => new {e.Fid, e.Portrait, e.DiscoveredAt, e.ModeratorType});
        b.Entity<Forum>().ToTable("tbm_forum");
    }
#pragma warning restore IDE0058 // Expression value is never used

    public void TimestampingEntities() =>
        // https://www.entityframeworktutorial.net/faq/set-created-and-modified-date-in-efcore.aspx
        ChangeTracker.Entries<ITimestampingEntity>().ForEach(e =>
        {
            Helper.GetNowTimestamp(out var now);
            var originalEntityState = e.State; // copy e.State since it might change after any prop value updated
            var createdAtProp = e.Property(ie => ie.CreatedAt);
            var updatedAtProp = e.Property(ie => ie.UpdatedAt);
            var lastSeenAtProp = e.Entity is IPost ? e.Property(ie => ((IPost)ie).LastSeenAt) : null;

            switch (originalEntityState)
            { // mutates Entry.CurrentValue will always update Entry.IsModified
                // and value of corresponding field in entity class instance that ChangeTracker references to(aka Entry.Entity)
                // while mutating Entry.Entity.Field requires (im|ex)plicitly invoking DetectChanges() to update Entry.CurrentValue and IsModified
                case EntityState.Added:
                    createdAtProp.CurrentValue = now;
                    break;
                case EntityState.Modified when createdAtProp.CurrentValue != now:
                    updatedAtProp.CurrentValue = now;
                    break;
            }
            if (lastSeenAtProp != null)
            {
                lastSeenAtProp.CurrentValue = originalEntityState switch
                {
                    EntityState.Unchanged => now, // updatedAt won't change when entity is unchanged
                    EntityState.Modified => null, // null means it's same with updatedAt
                    _ => lastSeenAtProp.CurrentValue
                };
            }
        });
}
