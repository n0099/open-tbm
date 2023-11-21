using System.Data;
using System.Data.Common;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;
using NLog.Extensions.Logging;
using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.Shared;

public abstract class TbmDbContext : DbContext
{
    protected static readonly SelectForUpdateCommandInterceptor SelectForUpdateCommandInterceptorInstance = new();

    protected sealed class SelectForUpdateCommandInterceptor : DbCommandInterceptor
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
public class TbmDbContext<TModelCacheKeyFactory> : TbmDbContext
    where TModelCacheKeyFactory : class, IModelCacheKeyFactory
{
    public required IConfiguration Config { private get; init; }
    public DbSet<ImageInReply> ImageInReplies => Set<ImageInReply>();
    public DbSet<ReplyContentImage> ReplyContentImages => Set<ReplyContentImage>();

#pragma warning disable IDE0058 // Expression value is never used
#pragma warning disable S927 // Parameter names should match base declaration and other partial definitions
    protected override void OnConfiguring(DbContextOptionsBuilder options)
    {
        var connectionStr = Config.GetConnectionString("Main");
        options.UseMySql(connectionStr!, ServerVersion.AutoDetect(connectionStr), OnConfiguringMysql)
            .ReplaceService<IModelCacheKeyFactory, TModelCacheKeyFactory>()
            .AddInterceptors(SelectForUpdateCommandInterceptorInstance)
            .UseCamelCaseNamingConvention();

        var dbSettings = Config.GetSection("DbSettings");
#pragma warning disable IDISP004 // Don't ignore created IDisposable
        options.UseLoggerFactory(LoggerFactory.Create(builder =>
            builder.AddNLog(new NLogProviderOptions {RemoveLoggerFactoryFilter = false})
                .SetMinimumLevel((LogLevel)NLog.LogLevel.FromString(
                    dbSettings.GetValue("LogLevel", "Trace")).Ordinal)));
#pragma warning restore IDISP004 // Don't ignore created IDisposable
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
#pragma warning restore S927 // Parameter names should match base declaration and other partial definitions
#pragma warning restore IDE0058 // Expression value is never used

    protected virtual void OnConfiguringMysql(MySqlDbContextOptionsBuilder builder) { }
}
