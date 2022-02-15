using System;

namespace tbm.Crawler
{
    public abstract class PostRevision : ICloneable
    {
        public object Clone() => MemberwiseClone();
        public uint Time { get; set; }
    }
}
