using System.Diagnostics;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.Unicode;
using Microsoft.EntityFrameworkCore.ChangeTracking;
using tbm.Shared.Db;

namespace tbm.Shared;

public abstract class TransformEntityWorker()
    : ErrorableWorker(shouldExitOnException: true, shouldExitOnFinish: true)
{
    protected static readonly JsonSerializerOptions JsonSerializerOptions = new()
    {
        IncludeFields = true,
        Encoder = JavaScriptEncoder.Create(UnicodeRanges.All)
    };
}

public abstract class TransformEntityWorker<TDbContext, TReadingEntity, TWritingEntity, TExceptionId>(
    ILogger<TransformEntityWorker<TDbContext, TReadingEntity, TWritingEntity, TExceptionId>> logger)
    : TransformEntityWorker
    where TDbContext : TbmDbContext
    where TReadingEntity : class
    where TWritingEntity : class
{
    protected async Task Transform(
        Func<TDbContext> dbContextFactory,
        int saveWritingEntitiesBatchSize,
        Func<TReadingEntity, TExceptionId> readingEntityExceptionIdSelector,
        Func<TReadingEntity, TWritingEntity> writingEntityFactory,
        Action<TReadingEntity, TWritingEntity> writingEntityMutator,
        Action<TDbContext, IReadOnlyCollection<EntityEntry<TWritingEntity>>> writingEntityEntriesAction,
        Action onPostSaveChanges,
        CancellationToken stoppingToken = default)
    {
        var processedEntityCount = 0;
        var stopwatch = new Stopwatch();
        stopwatch.Start();
        using var process = Process.GetCurrentProcess();
        var exceptions = new Dictionary<string, (int Times, TExceptionId LastId, string StackTrace)>();

        var readingDb = dbContextFactory();
        var writingDb = dbContextFactory();
        var readingEntities =
            from e in readingDb.Set<TReadingEntity>().AsNoTracking() select e;
        var writingEntityEntries = new List<EntityEntry<TWritingEntity>>(saveWritingEntitiesBatchSize);

        async Task SaveThenLog(int processedCount, Process currentProcess)
        {
            writingEntityEntriesAction(writingDb, writingEntityEntries);
            var updatedEntityCount = await writingDb.SaveChangesAsync(stoppingToken);
            writingDb.ChangeTracker.Clear();
            writingEntityEntries.Clear();
            onPostSaveChanges();

            logger.LogTrace("processedEntityCount:{} updatedEntityCount:{} elapsed:{}ms processMemory:{:F2}MiB exceptions:{}",
                processedCount, updatedEntityCount,
                stopwatch.ElapsedMilliseconds,
                currentProcess.PrivateMemorySize64 / 1024f / 1024,
                JsonSerializer.Serialize(exceptions, JsonSerializerOptions));
            stopwatch.Restart();
        }

        foreach (var readingEntity in readingEntities)
        {
            processedEntityCount++;
            if (processedEntityCount % saveWritingEntitiesBatchSize == 0)
                await SaveThenLog(processedEntityCount, process);
            if (stoppingToken.IsCancellationRequested) break;
            try
            {
                var writingEntity = writingEntityFactory(readingEntity);
                writingEntityEntries.Add(writingDb.Set<TWritingEntity>().Attach(writingEntity));
                writingEntityMutator(readingEntity, writingEntity);
            }
            catch (Exception e)
            {
                var exceptionKey = e.GetType().FullName + ": " + e.Message;
                var newTuple = (1, LastId: readingEntityExceptionIdSelector(readingEntity), StackTrace: e.StackTrace ?? "");
                if (!exceptions.TryAdd(exceptionKey, newTuple))
                {
                    var existing = exceptions[exceptionKey];
                    existing.Times++;
                    existing.LastId = newTuple.LastId;
                    existing.StackTrace = newTuple.StackTrace;
                    exceptions[exceptionKey] = existing;
                }
            }
        }

        await SaveThenLog(processedEntityCount, process);
    }
}
