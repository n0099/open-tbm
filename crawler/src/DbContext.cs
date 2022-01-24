using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.Extensions.Configuration;

namespace tbm
{
    public class DbContext : Microsoft.EntityFrameworkCore.DbContext
    {
        public DbSet<ThreadPost> Threads => Set<ThreadPost>();
        private readonly IConfiguration _config;
        public uint Fid { get; }

        public delegate DbContext New(uint fid);

        public DbContext(IConfiguration config, uint fid)
        {
            _config = config;
            Fid = fid;
        }

        protected override void OnModelCreating(ModelBuilder modelBuilder) =>
            modelBuilder.Entity<ThreadPost>().ToTable($"tbm_f{Fid}_threads");

        protected override void OnConfiguring(DbContextOptionsBuilder options)
        {
            var connStr = _config["ConnectionStrings:Main"];
            options.UseMySql(connStr, ServerVersion.AutoDetect(connStr))
                .ReplaceService<IModelCacheKeyFactory, ModelWithFidCacheKeyFactory>();
#if DEBUG
            options.EnableDetailedErrors().EnableSensitiveDataLogging();
#endif
        }
    }

    class ModelWithFidCacheKeyFactory : IModelCacheKeyFactory
    { // https://stackoverflow.com/questions/51864015/entity-framework-map-model-class-to-table-at-run-time/51899590#51899590
        // https://docs.microsoft.com/en-us/ef/core/modeling/dynamic-model
        public object Create(Microsoft.EntityFrameworkCore.DbContext context) => Create(context, false);
        public object Create(Microsoft.EntityFrameworkCore.DbContext context, bool designTime) =>
            context is DbContext dbContext
                ? (context.GetType(), dbContext.Fid, designTime)
                : context.GetType();
    }
}
