using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.Crawler.Db
{
    public class TbmDbContext : DbContext
    {
        private readonly IConfiguration _config;
        public Fid Fid { get; }
        public DbSet<TiebaUser> Users => Set<TiebaUser>();
        public DbSet<AuthorExpGradeRevision> AuthorExpGradeRevisions => Set<AuthorExpGradeRevision>();
        public DbSet<AuthorManagerTypeRevision> AuthorManagerTypeRevisions => Set<AuthorManagerTypeRevision>();
        public DbSet<ThreadPost> Threads => Set<ThreadPost>();
        public DbSet<ThreadMissingFirstReply> ThreadMissingFirstReplies => Set<ThreadMissingFirstReply>();
        public DbSet<ReplyPost> Replies => Set<ReplyPost>();
        public DbSet<ReplySignature> ReplySignatures => Set<ReplySignature>();
        public DbSet<ReplyContent> ReplyContents => Set<ReplyContent>();
        public DbSet<SubReplyPost> SubReplies => Set<SubReplyPost>();
        public DbSet<SubReplyContent> SubReplyContents => Set<SubReplyContent>();
        public DbSet<Forum> Forum => Set<Forum>();

        public delegate TbmDbContext New(Fid fid);

        public TbmDbContext(IConfiguration config, Fid fid)
        {
            _config = config;
            Fid = fid;
        }

#pragma warning disable IDE0058 // Expression value is never used
        protected override void OnModelCreating(ModelBuilder b)
        {
            b.Entity<TiebaUser>().ToTable("tbmc_user");
            b.Entity<ThreadPost>().ToTable($"tbmc_f{Fid}_thread");
            b.Entity<ThreadMissingFirstReply>().ToTable("tbmc_thread_missingFirstReply");
            b.Entity<ReplyPost>().ToTable($"tbmc_f{Fid}_reply");
            b.Entity<ReplySignature>().ToTable("tbmc_reply_signature").HasKey(e => new {e.SignatureId, e.SignatureMd5});
            b.Entity<ReplyContent>().ToTable($"tbmc_f{Fid}_reply_content");
            b.Entity<SubReplyPost>().ToTable($"tbmc_f{Fid}_subReply");
            b.Entity<SubReplyContent>().ToTable($"tbmc_f{Fid}_subReply_content");
            b.Entity<ThreadRevision>().ToTable("tbmc_revision_thread").HasKey(e => new {e.Tid, e.Time});
            b.Entity<ReplyRevision>().ToTable("tbmc_revision_reply").HasKey(e => new {e.Pid, e.Time});
            b.Entity<SubReplyRevision>().ToTable("tbmc_revision_subReply").HasKey(e => new {e.Spid, e.Time});
            b.Entity<UserRevision>().ToTable("tbmc_revision_user").HasKey(e => new {e.Uid, e.Time});
            b.Entity<AuthorExpGradeRevision>().ToTable("tbmc_revision_authorExpGrade").HasKey(e => new {e.Fid, e.Uid, e.Time});
            b.Entity<AuthorManagerTypeRevision>().ToTable("tbmc_revision_authorManagerType").HasKey(e => new {e.Fid, e.Uid, e.Time});
            b.Entity<Forum>().ToTable("tbm_forum");
        }

        protected override void OnConfiguring(DbContextOptionsBuilder options)
        {
            var connectionStr = _config.GetConnectionString("Main");
            options.UseMySql(connectionStr, ServerVersion.AutoDetect(connectionStr))
                .ReplaceService<IModelCacheKeyFactory, ModelWithFidCacheKeyFactory>()
                .UseCamelCaseNamingConvention();

            var dbSettings = _config.GetSection("DbSettings");
            options.UseLoggerFactory(LoggerFactory.Create(builder => builder.AddNLog(new NLogProviderOptions {RemoveLoggerFactoryFilter = false})
                .SetMinimumLevel((LogLevel)NLog.LogLevel.FromString(dbSettings.GetValue("LogLevel", "Trace")).Ordinal)));
            if (dbSettings.GetValue("EnableDetailedErrors", false)) options.EnableDetailedErrors();
            if (dbSettings.GetValue("EnableSensitiveDataLogging", false)) options.EnableSensitiveDataLogging();
        }
#pragma warning restore IDE0058 // Expression value is never used

        public int SaveChangesWithTimestamp()
        { // https://www.entityframeworktutorial.net/faq/set-created-and-modified-date-in-efcore.aspx
            ChangeTracker.Entries<IEntityWithTimestampFields>().ForEach(e =>
            {
                var now = (Time)DateTimeOffset.Now.ToUnixTimeSeconds();
                var originalEntityState = e.State; // copy e.State since it might change after any prop value updated
                var createdAtProp = e.Property(ie => ie.CreatedAt);
                var updatedAtProp = e.Property(ie => ie.UpdatedAt);
                var lastSeenProp = e.Entity is IPost ? e.Property(ie => ((IPost)ie).LastSeen) : null;

                // mutates Entry.CurrentValue will always update Entry.IsModified
                // while mutating Entry.Entity.Field requires invoking ChangeTracker.DetectChanges()
                if (originalEntityState == EntityState.Added) createdAtProp.CurrentValue = now;

                if (lastSeenProp != null)
                {
                    lastSeenProp.CurrentValue = originalEntityState switch
                    {
                        EntityState.Unchanged => now, // updatedAt won't change when entity is unchanged
                        EntityState.Modified => null, // null means it's same with updatedAt
                        _ => lastSeenProp.CurrentValue
                    };
                }

                if (originalEntityState == EntityState.Modified && createdAtProp.CurrentValue != now)
                    updatedAtProp.CurrentValue = now;
            });
            return base.SaveChanges();
        }

        private class ModelWithFidCacheKeyFactory : IModelCacheKeyFactory
        { // https://stackoverflow.com/questions/51864015/entity-framework-map-model-class-to-table-at-run-time/51899590#51899590
            // https://docs.microsoft.com/en-us/ef/core/modeling/dynamic-model
            public object Create(DbContext context) => Create(context, false);
            public object Create(DbContext context, bool designTime) =>
                context is TbmDbContext dbContext
                    ? (context.GetType(), dbContext.Fid, designTime)
                    : context.GetType();
        }
    }
}
