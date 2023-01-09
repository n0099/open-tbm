namespace tbm.Crawler
{
    public abstract class WithLogTrace
    {
        private IConfigurationSection? _config;
        private readonly Timer _timerLogTrace = new() {Enabled = true};

        protected abstract void LogTrace();

        protected void InitLogTrace(IConfigurationSection config)
        {
            _config = config.GetSection("LogTrace");
            _timerLogTrace.Interval = _config.GetValue("LogIntervalMs", 1000);
            _timerLogTrace.Elapsed += (_, _) => LogTrace();
        }

        protected bool ShouldLogTrace()
        {
            if (_config == null || !_config.GetValue("Enabled", false)) return false;
            _timerLogTrace.Interval = _config.GetValue("LogIntervalMs", 1000);
            return true;
        }
    }
}
