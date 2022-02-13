using System;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;
using NLog.Extensions.Logging;

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

        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            modelBuilder.Entity<TiebaUser>().ToTable("tbm_tiebaUsers");
            modelBuilder.Entity<ThreadPost>().ToTable($"tbm_f{Fid}_threads");
            modelBuilder.Entity<ReplyPost>().ToTable($"tbm_f{Fid}_replies");
            modelBuilder.Entity<SubReplyPost>().ToTable($"tbm_f{Fid}_subReplies");
            modelBuilder.Entity<ThreadRevision>().ToTable("tbm_revision_threads").HasKey(e => new { e.Time, e.Tid });
            modelBuilder.Entity<UserRevision>().ToTable("tbm_revision_users").HasKey(e => new { e.Time, e.Uid });
            modelBuilder.Entity<PostIndex>().ToTable("tbm_postsIndex").HasKey(e => new { e.Tid, e.Pid, e.Spid });
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
    }

    class ModelWithFidCacheKeyFactory : IModelCacheKeyFactory
    { // https://stackoverflow.com/questions/51864015/entity-framework-map-model-class-to-table-at-run-time/51899590#51899590
        // https://docs.microsoft.com/en-us/ef/core/modeling/dynamic-model
        public object Create(DbContext context) => Create(context, false);
        public object Create(DbContext context, bool designTime) =>
            context is TbmDbContext dbContext
                ? (context.GetType(), dbContext.Fid, designTime)
                : context.GetType();
    }
}
