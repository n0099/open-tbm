namespace tbm.Crawler;

public abstract class WithLogTrace
{
    private readonly IConfigurationSection _config;
    private readonly Timer _timerLogTrace = new() {Enabled = true};

    protected WithLogTrace(IConfiguration config, string section)
    {
        _config = config.GetSection(section).GetSection("LogTrace");
        _timerLogTrace.Interval = _config.GetValue("LogIntervalMs", 1000);
        _timerLogTrace.Elapsed += (_, _) => LogTrace();
    }

    protected abstract void LogTrace();

    protected bool ShouldLogTrace()
    {
        if (!_config.GetValue("Enabled", false)) return false;
        _timerLogTrace.Interval = _config.GetValue("LogIntervalMs", 1000);
        return true;
    }
}
