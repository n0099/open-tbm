using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.Crawler
{
    public class TbmDbContext : DbContext
    {
        private readonly ILogger<TbmDbContext> _logger;
        private readonly IConfiguration _config;
        private Fid Fid { get; }
        public DbSet<TiebaUser> Users => Set<TiebaUser>();
        public DbSet<ThreadPost> Threads => Set<ThreadPost>();
        public DbSet<ReplyPost> Replies => Set<ReplyPost>();
        public DbSet<ReplySignature> ReplySignatures => Set<ReplySignature>();
        public DbSet<ReplyContent> ReplyContents => Set<ReplyContent>();
        public DbSet<SubReplyPost> SubReplies => Set<SubReplyPost>();
        public DbSet<SubReplyContent> SubReplyContents => Set<SubReplyContent>();
        public DbSet<ForumInfo> ForumsInfo => Set<ForumInfo>();

        public delegate TbmDbContext New(Fid fid);

        public TbmDbContext(ILogger<TbmDbContext> logger, IConfiguration config, Fid fid)
        {
            _logger = logger;
            _config = config;
            Fid = fid;
        }

#pragma warning disable IDE0058 // Expression value is never used
        protected override void OnModelCreating(ModelBuilder b)
        {
            b.Entity<TiebaUser>().ToTable("tbm_tiebaUsers");
            b.Entity<ThreadPost>().ToTable($"tbm_f{Fid}_threads");
            b.Entity<ReplyPost>().ToTable($"tbm_f{Fid}_replies");
            b.Entity<ReplySignature>().ToTable("tbm_reply_signatures").HasKey(e => new {e.SignatureId, e.SignatureMd5});
            b.Entity<ReplyContent>().ToTable($"tbm_f{Fid}_replies_content");
            b.Entity<SubReplyPost>().ToTable($"tbm_f{Fid}_subReplies");
            b.Entity<SubReplyContent>().ToTable($"tbm_f{Fid}_subReplies_content");
            b.Entity<ThreadRevision>().ToTable("tbm_revision_threads").HasKey(e => new {e.Tid, e.Time});
            b.Entity<ReplyRevision>().ToTable("tbm_revision_replies").HasKey(e => new {e.Pid, e.Time});
            b.Entity<SubReplyRevision>().ToTable("tbm_revision_subReplies").HasKey(e => new {e.Spid, e.Time});
            b.Entity<UserRevision>().ToTable("tbm_revision_users").HasKey(e => new {e.Uid, e.Time});
            b.Entity<ForumInfo>().ToTable("tbm_forumsInfo");
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

        public int SaveChangesWithTimestamping()
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

                // prevent overwrite existing future timestamp, this will happens when a record is updated >=3 times within a second
                if (updatedAtProp.CurrentValue > now) return;

                if (originalEntityState == EntityState.Modified && createdAtProp.CurrentValue != now)
                    updatedAtProp.CurrentValue = now;

                if (originalEntityState != EntityState.Modified || updatedAtProp.IsModified) return;
                var changedPropsValueDiff = e.Properties.Where(p => p.IsModified) // not using lazy eval to prevent including the updatedAt field itself
                    .Select(p => new {p.Metadata.Name, New = p.CurrentValue, Old = p.OriginalValue}).ToList();
                updatedAtProp.CurrentValue ??= now;
                do
                {
                    updatedAtProp.CurrentValue += 1;
                } while (updatedAtProp.CurrentValue == (updatedAtProp.OriginalValue ?? createdAtProp.CurrentValue));
                _logger.LogWarning("Detected unchanged updatedAt timestamp for updating record with following fields changed={}, new record={}, old record={}. " +
                                   "This means the record is updated more than one time within one second, " +
                                   "which usually caused by a different response of the same resource from tieba. " +
                                   "In order to prevent any possible duplicate keys conflicts from other revision tables update in the future, " +
                                   "we've increased the value of updatedAt field back to the future.",
                    Helper.UnescapedJsonSerialize(changedPropsValueDiff), Helper.UnescapedJsonSerialize(e.CurrentValues.ToObject()), Helper.UnescapedJsonSerialize(e.OriginalValues.ToObject()));
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
