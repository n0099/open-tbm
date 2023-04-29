using System.Data.Common;
using Microsoft.EntityFrameworkCore.Diagnostics;
using Microsoft.EntityFrameworkCore.Infrastructure;
using NLog.Extensions.Logging;
using LogLevel = Microsoft.Extensions.Logging.LogLevel;

namespace tbm.ImagePipeline.Db;

public class TbmDbContext : DbContext
{
    private readonly IConfiguration _config;
    private static readonly SelectForUpdateCommandInterceptor SelectForUpdateCommandInterceptorInstance = new();
    public string Script { get; }
    public DbSet<TiebaImage> Images => Set<TiebaImage>();
    public DbSet<TiebaImageOcrBoxes> ImageOcrBoxes => Set<TiebaImageOcrBoxes>();
    public DbSet<TiebaImageOcrLines> ImageOcrLines => Set<TiebaImageOcrLines>();

    public delegate TbmDbContext New(string script);

    public TbmDbContext(IConfiguration config, string script)
    {
        _config = config;
        Script = script;
    }

#pragma warning disable IDE0058 // Expression value is never used
    protected override void OnModelCreating(ModelBuilder b)
    {
        b.Entity<TiebaImage>().ToTable("tbmc_image");
        b.Entity<TiebaImageOcrBoxes>().ToTable("tbmc_image_ocr_boxes").HasKey(e =>
            new {e.ImageId, e.CenterPointX, e.CenterPointY, e.Width, e.Height, e.RotationDegrees, e.Recognizer, e.Script});
        b.Entity<TiebaImageOcrLines>().ToTable("tbmc_image_ocr_lines").HasKey(e => new {e.ImageId, e.Script});
    }

    protected override void OnConfiguring(DbContextOptionsBuilder options)
    {
        var connectionStr = _config.GetConnectionString("Main");
        options.UseMySql(connectionStr!, ServerVersion.AutoDetect(connectionStr))
            .ReplaceService<IModelCacheKeyFactory, ModelWithFidCacheKeyFactory>()
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
#pragma warning restore IDE0058 // Expression value is never used

    private class ModelWithFidCacheKeyFactory : IModelCacheKeyFactory
    { // https://stackoverflow.com/questions/51864015/entity-framework-map-model-class-to-table-at-run-time/51899590#51899590
        // https://docs.microsoft.com/en-us/ef/core/modeling/dynamic-model
        public object Create(DbContext context) => Create(context, false);
        public object Create(DbContext context, bool designTime) =>
            context is TbmDbContext dbContext
                ? (context.GetType(), dbContext.Script, designTime)
                : context.GetType();
    }

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
