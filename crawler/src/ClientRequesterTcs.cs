using System;
using System.Collections.Concurrent;
using System.Threading.Tasks;
using System.Timers;

namespace tbm
{
    public static class ClientRequesterTcs
    {
        private static readonly ConcurrentQueue<TaskCompletionSource> Queue = new();
        private static readonly Timer Timer = new();
        private static double _maxRps;
        public static int QueueLength { get => Queue.Count; }
        public static double MaxRps
        {
            get => _maxRps;
            set
            {
                _maxRps = value;
                Timer.Interval = 1000 / value;
                Console.WriteLine($"{QueueLength} {Timer.Interval:F2}");
            }
        }

        static ClientRequesterTcs()
        {
            MaxRps = 15;
            Timer.Elapsed += (_, e) =>
            {
                if (Queue.TryDequeue(out var tcs)) tcs?.SetResult();
            };
            Timer.Enabled = true;
        }

        public static void Enqueue(TaskCompletionSource tcs) => Queue.Enqueue(tcs);
    }
}
