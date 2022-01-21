using System;
using System.Collections.Concurrent;
using System.Diagnostics;
using System.Threading;
using System.Threading.Tasks;
using System.Timers;

namespace tbm
{
    public static class ClientRequesterTcs
    {
        private static readonly ConcurrentQueue<TaskCompletionSource> Queue = new();
        private static readonly Timer Timer = new();
        private static double _maxRps;
        private const int InitialRps = 15;
        private static readonly Stopwatch Stopwatch = new();
        private static int _requestCounter;

        public static int QueueLength { get => Queue.Count; }
        public static double AverageRps
        {
            get => (double)_requestCounter / Stopwatch.ElapsedMilliseconds * 1000;
        }

        public static double MaxRps
        {
            get => _maxRps;
            private set
            {
                _maxRps = value;
                if ((uint)Timer.Interval != (uint)(1000 / value))
                { // only update interval with a truncated integer to prevent frequently change it
                  // which will cause the increment of real rps can't keep up with _maxRps with long queue length
                    Timer.Interval = 1000 / value;
                }
                Interlocked.Increment(ref _requestCounter);
            }
        }

        static ClientRequesterTcs()
        {
            Stopwatch.Start();
            MaxRps = InitialRps;
            Timer.Elapsed += (_, _) =>
            {
                if (Queue.TryDequeue(out var tcs)) tcs?.SetResult();
            };
            Timer.Enabled = true;
        }

        public static void Increase() => MaxRps = Math.Min(1000, MaxRps + Math.Log10(MaxRps) * 0.01);
        public static void Decrease() => MaxRps = Math.Max(1, MaxRps - 1);

        public static void Wait()
        { // https://devblogs.microsoft.com/premier-developer/the-danger-of-taskcompletionsourcet-class/
            var tcs = new TaskCompletionSource(TaskCreationOptions.RunContinuationsAsynchronously);
            Queue.Enqueue(tcs);
            tcs.Task.Wait();
        }

        public static void ResetAverageRps()
        {
            Interlocked.Exchange(ref _requestCounter, 0);
            Stopwatch.Restart();
        }
    }
}
