using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.Extensions.Configuration;

namespace tbm.Crawler
{
    public class TbmDbContext : DbContext
    {
        private readonly string _connectionStr;
        public DbSet<ThreadPost> Threads => Set<ThreadPost>();
        public uint Fid { get; }

        public delegate TbmDbContext New(uint fid);

        public TbmDbContext(IConfiguration config, uint fid)
        {
            _connectionStr = config.GetConnectionString("Main");
            Fid = fid;
        }

        protected override void OnModelCreating(ModelBuilder modelBuilder) =>
            modelBuilder.Entity<ThreadPost>().ToTable($"tbm_f{Fid}_threads");

        protected override void OnConfiguring(DbContextOptionsBuilder options)
        {
            options.UseMySql(_connectionStr, ServerVersion.AutoDetect(_connectionStr))
                .ReplaceService<IModelCacheKeyFactory, ModelWithFidCacheKeyFactory>();
#if DEBUG
            options.EnableDetailedErrors().EnableSensitiveDataLogging();
#endif
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
