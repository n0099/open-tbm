using System.Diagnostics;
using System.IO.Hashing;
using System.Text.Encodings.Web;

namespace tbm.ImagePipeline;

public class MigrationWorker : BackgroundService
{
    private readonly ILogger<MigrationWorker> _logger;
    private readonly ILifetimeScope _scope0;
    private readonly IHostApplicationLifetime _applicationLifetime;

    public MigrationWorker(ILogger<MigrationWorker> logger, ILifetimeScope scope0, IHostApplicationLifetime applicationLifetime)
    {
        _logger = logger;
        _scope0 = scope0;
        _applicationLifetime = applicationLifetime;
    }

    private static readonly JsonSerializerOptions JsonSerializerOptions = new()
    {
        IncludeFields = true,
        Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping,
        // DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingDefault
    };

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        await using var scope1 = _scope0.BeginLifetimeScope();
        var db = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
        var db2 = scope1.Resolve<ImagePipelineDbContext.NewDefault>()();
        var existingEntities =
            from e in db.Set<ImageMetadata.Exif>().AsNoTracking() select e;
        var i = 0;
        using var process = Process.GetCurrentProcess();
        var stopwatch = new Stopwatch();
        stopwatch.Start();
        var exceptions = new Dictionary<string, (uint Times, ulong LastId, string StackTrace)>();
        var newEntities = new List<ImageMetadata.Exif>();
        void SaveAndLog()
        {
            db2.Set<ImageMetadata.Exif>().UpdateRange(newEntities);
            foreach (var e in db2.ChangeTracker.Entries<ImageMetadata.Exif>())
            {
                e.Property(nameof(ImageMetadata.Exif.RawBytes)).IsModified = false;
            }
            var entitiesUpdated = db2.SaveChanges();
            newEntities.Clear();
            db2.ChangeTracker.Clear();

            _logger.LogTrace("i:{} entitiesUpdated:{} elapsed:{}ms mem:{}mb exceptions:{}",
                i, entitiesUpdated,
                stopwatch.ElapsedMilliseconds,
                process.PrivateMemorySize64 / 1024 / 1024,
                JsonSerializer.Serialize(exceptions, JsonSerializerOptions));
            stopwatch.Restart();
        }
        foreach (var entity in existingEntities)
        {
            i++;
            if (i % 10000 == 0) SaveAndLog();
            if (stoppingToken.IsCancellationRequested) break;
            try
            {
                var newEntity = MetadataConsumer.CreateEmbeddedExifFromProfile(new(entity.RawBytes));
                newEntity.ImageId = entity.ImageId;
                newEntity.XxHash3 = XxHash3.HashToUInt64(entity.RawBytes);
                newEntities.Add(newEntity);
            }
            catch (Exception e)
            {
                var eKey = e.GetType().FullName + ": " + e.Message;
                if (!exceptions.TryAdd(eKey, (1, entity.ImageId, e.StackTrace ?? "")))
                {
                    var ex = exceptions[eKey];
                    ex.Times++;
                    ex.LastId = entity.ImageId;
                    ex.StackTrace = e.StackTrace ?? "";
                    exceptions[eKey] = ex;
                }
            }
        }
        SaveAndLog();
        _applicationLifetime.StopApplication();
    }
}
