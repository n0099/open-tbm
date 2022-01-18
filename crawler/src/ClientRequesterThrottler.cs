using System;
using System.Diagnostics;
using System.Threading;

namespace tbm
{
    public static class ClientRequesterThrottler
    {
        public static ushort RequestedTimesInPrevSec { get; private set; }
        public static double MaxRps { get; set; } = 100;
        private static readonly Stopwatch Timer = Stopwatch.StartNew();
        private static readonly Random Rand = new();

        public static ClientRequester Bind(ClientRequester clientRequester)
        {
            ShouldReset();
            RequestedTimesInPrevSec++;
            while (RequestedTimesInPrevSec >= MaxRps)
            {
                Thread.Sleep(100);
                ShouldReset();
            }
            return clientRequester;
        }

        private static void ShouldReset()
        {
            if (Timer.Elapsed.Seconds <= 1) return;
            Timer.Restart();
            RequestedTimesInPrevSec = 0;
        }
    }
}
