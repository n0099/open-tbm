using System.Data.Common;
using System.Data;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;
using NLog.Extensions.Logging;
using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.Shared;

public class TbmDbContext<TModelCacheKeyFactory> : DbContext where TModelCacheKeyFactory : class, IModelCacheKeyFactory
{
    public DbSet<ImageInReply> ImageInReplies => Set<ImageInReply>();
    public DbSet<ReplyContentImage> ReplyContentImages => Set<ReplyContentImage>();

    private readonly IConfiguration _config;
    private static readonly SelectForUpdateCommandInterceptor SelectForUpdateCommandInterceptorInstance = new();

    public TbmDbContext(IConfiguration config) => _config = config;

#pragma warning disable IDE0058 // Expression value is never used
    protected override void OnConfiguring(DbContextOptionsBuilder options)
    {
        var connectionStr = _config.GetConnectionString("Main");
        options.UseMySql(connectionStr!, ServerVersion.AutoDetect(connectionStr), OnConfiguringMysql)
            .ReplaceService<IModelCacheKeyFactory, TModelCacheKeyFactory>()
            .AddInterceptors(SelectForUpdateCommandInterceptorInstance)
            .UseCamelCaseNamingConvention();

        var dbSettings = _config.GetSection("DbSettings");
        options.UseLoggerFactory(LoggerFactory.Create(builder =>
            builder.AddNLog(new NLogProviderOptions {RemoveLoggerFactoryFilter = false})
                .SetMinimumLevel((LogLevel)NLog.LogLevel.FromString(
                    dbSettings.GetValue("LogLevel", "Trace")).Ordinal)));
        if (dbSettings.GetValue("EnableDetailedErrors", false)) options.EnableDetailedErrors();
        if (dbSettings.GetValue("EnableSensitiveDataLogging", false)) options.EnableSensitiveDataLogging();
    }

    protected override void OnModelCreating(ModelBuilder b)
    {
        b.Entity<ImageInReply>().ToTable("tbmi_imageInReply");
        b.Entity<ReplyContentImage>().HasKey(e => new {e.Pid, e.ImageId});
        b.Entity<ReplyContentImage>().HasOne(e => e.ImageInReply).WithMany();
    }

    protected void OnModelCreatingWithFid(ModelBuilder b, uint fid) =>
        b.Entity<ReplyContentImage>().ToTable($"tbmc_f{fid}_reply_content_image");
#pragma warning restore IDE0058 // Expression value is never used

    protected virtual void OnConfiguringMysql(MySqlDbContextOptionsBuilder builder) { }

    private class SelectForUpdateCommandInterceptor : DbCommandInterceptor
    { // https://stackoverflow.com/questions/37984312/how-to-implement-select-for-update-in-ef-core/75086260#75086260
        public override InterceptionResult<object> ScalarExecuting(DbCommand command, CommandEventData eventData, InterceptionResult<object> result)
        {
            ManipulateCommand(command);
            return result;
        }

        public override ValueTask<InterceptionResult<object>> ScalarExecutingAsync(DbCommand command, CommandEventData eventData, InterceptionResult<object> result, CancellationToken cancellationToken = default)
        {
            ManipulateCommand(command);
            return new(result);
        }

        public override InterceptionResult<DbDataReader> ReaderExecuting(DbCommand command, CommandEventData eventData, InterceptionResult<DbDataReader> result)
        {
            ManipulateCommand(command);
            return result;
        }

        public override ValueTask<InterceptionResult<DbDataReader>> ReaderExecutingAsync(DbCommand command, CommandEventData eventData, InterceptionResult<DbDataReader> result, CancellationToken cancellationToken = default)
        {
            ManipulateCommand(command);
            return new(result);
        }

        private static void ManipulateCommand(IDbCommand command)
        {
            if (command.CommandText.StartsWith("-- ForUpdate", StringComparison.Ordinal))
                command.CommandText += " FOR UPDATE";
        }
    }
}
