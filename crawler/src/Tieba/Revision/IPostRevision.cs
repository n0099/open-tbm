using System;

namespace tbm.Crawler
{
    public interface IPostRevision : ICloneable
    {
        public uint Time { get; set; }
        public ulong Tid { get; set; }
    }
}
