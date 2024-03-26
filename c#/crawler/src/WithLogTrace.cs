namespace tbm.Crawler;

#pragma warning disable S3881 // "IDisposable" should be implemented correctly
public abstract class WithLogTrace : IDisposable
#pragma warning restore S3881 // "IDisposable" should be implemented correctly
{
    private readonly IConfigurationSection _config;
    private readonly Timer _timer = new() {Enabled = true};

    protected WithLogTrace(IConfiguration config, string section)
    {
        _config = config.GetSection(section).GetSection("LogTrace");
        _timer.Interval = _config.GetValue("LogIntervalMs", 1000);
        _timer.Elapsed += (_, _) => LogTrace();
    }

    public virtual void Dispose()
    {
        GC.SuppressFinalize(this);
        _timer.Dispose();
    }

    protected abstract void LogTrace();

    protected bool ShouldLogTrace()
    {
        if (!_config.GetValue("Enabled", false)) return false;
        _timer.Interval = _config.GetValue("LogIntervalMs", 1000);
        return true;
    }
}
