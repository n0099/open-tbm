using Microsoft.EntityFrameworkCore;

namespace tbm
{
    public class DbContext : Microsoft.EntityFrameworkCore.DbContext
    {
        public DbSet<ThreadPost> Threads => Set<ThreadPost>();
        public int Fid { get; }

        public DbContext(int fid) => Fid = fid;

        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            modelBuilder.Entity<ThreadPost>().ToTable($"tbm_f{Fid}_threads");
        }

        protected override void OnConfiguring(DbContextOptionsBuilder optionsBuilder)
        {
            var conn = "server=localhost;user=root;password=;database=";
            optionsBuilder = optionsBuilder.UseMySql(conn, ServerVersion.AutoDetect(conn));
#if DEBUG
            optionsBuilder.EnableDetailedErrors().EnableSensitiveDataLogging();
#endif
        }
    }
}
