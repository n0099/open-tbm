using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.Crawler
{
    public class TbmDbContext : DbContext
    {
        public uint Fid { get; }
        public DbSet<TiebaUser> Users => Set<TiebaUser>();
        public DbSet<ThreadPost> Threads => Set<ThreadPost>();
        public DbSet<ReplyPost> Replies => Set<ReplyPost>();
        public DbSet<SubReplyPost> SubReplies => Set<SubReplyPost>();
        public DbSet<PostIndex> PostsIndex => Set<PostIndex>();
        private readonly IConfiguration _config;

        public delegate TbmDbContext New(uint fid);

        public TbmDbContext(IConfiguration config, uint fid)
        {
            _config = config;
            Fid = fid;
        }

#pragma warning disable IDE0058 // Expression value is never used
        protected override void OnModelCreating(ModelBuilder b)
        {
            b.Entity<TiebaUser>().ToTable("tbm_tiebaUsers");
            b.Entity<ThreadPost>().ToTable($"tbm_f{Fid}_threads");
            b.Entity<ReplyPost>().ToTable($"tbm_f{Fid}_replies");
            b.Entity<SubReplyPost>().ToTable($"tbm_f{Fid}_subReplies");
            b.Entity<ThreadRevision>().ToTable("tbm_revision_threads").HasKey(e => new { e.Tid, e.Time });
            b.Entity<ReplyRevision>().ToTable("tbm_revision_replies").HasKey(e => new { e.Pid, e.Time });
            b.Entity<SubReplyRevision>().ToTable("tbm_revision_subReplies").HasKey(e => new { e.Spid, e.Time });
            b.Entity<UserRevision>().ToTable("tbm_revision_users").HasKey(e => new { e.Uid, e.Time });
            b.Entity<PostIndex>().ToTable("tbm_postsIndex").HasKey(e => new { e.Tid, e.Pid, e.Spid });
        }

        protected override void OnConfiguring(DbContextOptionsBuilder options)
        {
            var connectionStr = _config.GetConnectionString("Main");
            options.UseMySql(connectionStr, ServerVersion.AutoDetect(connectionStr))
                .ReplaceService<IModelCacheKeyFactory, ModelWithFidCacheKeyFactory>()
                .UseCamelCaseNamingConvention();

            var dbSettings = _config.GetSection("DbSettings");
            options.UseLoggerFactory(LoggerFactory.Create(builder => builder.AddNLog()
                .SetMinimumLevel((LogLevel)NLog.LogLevel.FromString(dbSettings.GetValue("LogLevel", "Trace")).Ordinal)));
            if (dbSettings.GetValue("EnableDetailedErrors", false)) options.EnableDetailedErrors();
            if (dbSettings.GetValue("EnableSensitiveDataLogging", false)) options.EnableSensitiveDataLogging();
        }
#pragma warning restore IDE0058 // Expression value is never used

        public override int SaveChanges()
        { // https://www.entityframeworktutorial.net/faq/set-created-and-modified-date-in-efcore.aspx
            ChangeTracker.Entries().ForEach(e =>
            {
                if (e.Entity is not IEntityWithTimestampFields entity
                    || e.State is not (EntityState.Added or EntityState.Modified)) return;

                entity.UpdatedAt = (uint)DateTimeOffset.Now.ToUnixTimeSeconds();
                if (e.State == EntityState.Added)
                    entity.CreatedAt = (uint)DateTimeOffset.Now.ToUnixTimeSeconds();
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
