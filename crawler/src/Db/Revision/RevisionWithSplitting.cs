namespace tbm.Crawler.Db.Revision
{
    public abstract class RevisionWithSplitting<TSplitEntities> : IRevision
    {
        public uint TakenAt { get; set; }
        public ushort? NullFieldsBitMask { get; set; }

        private Dictionary<Type, TSplitEntities> SplitEntities { get; } = new();
        public IEnumerable<TSplitEntities> GetSplitEntities() => SplitEntities.Values;

        protected TValue? GetSplitEntityValue<TSplitEntity, TValue>(Func<TSplitEntity, TValue?> valueSelector)
            where TSplitEntity : class, TSplitEntities =>
            SplitEntities.ContainsKey(typeof(TSplitEntity))
                ? valueSelector((TSplitEntity)SplitEntities[typeof(TSplitEntity)]!)
                : default;

        protected void SetSplitEntityValue<TSplitEntity, TValue>(TValue? value,
            Action<TSplitEntity, TValue?> valueSetter, Func<TSplitEntity> entityFactory)
            where TSplitEntity : class, TSplitEntities
        {
            if (SplitEntities.ContainsKey(typeof(TSplitEntity)))
                valueSetter((TSplitEntity)SplitEntities[typeof(TSplitEntity)]!, value);
            else
                SplitEntities[typeof(TSplitEntity)] = entityFactory();
        }
    }
}
