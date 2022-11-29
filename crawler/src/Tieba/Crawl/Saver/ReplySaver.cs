namespace tbm.Crawler
{
    public class ReplySaver : BaseSaver<ReplyPost>
    {
        public override FieldChangeIgnoranceCallbackRecord TiebaUserFieldChangeIgnorance { get; } = new(
            Update: (_, propertyName, _, currentValue) =>
                // the value of IconInfo in response of reply crawler might be null even if the user haven't change his icon info
                propertyName == nameof(TiebaUser.IconInfo) && currentValue is null,
            (_, propertyName, originalValue, currentValue) =>
                // the value of user gender in thread response might be 0 but in reply response it won't be 0
                propertyName == nameof(TiebaUser.Gender) && (ushort?)originalValue is 0 && (ushort?)currentValue is not 0);

        protected override Dictionary<string, ushort> RevisionNullFieldsBitMasks { get; } = new()
        {
            {nameof(ReplyPost.AuthorManagerType), 1},
            {nameof(ReplyPost.SubReplyNum),       1 << 1},
            {nameof(ReplyPost.IsFold),            1 << 2},
            {nameof(ReplyPost.AgreeNum),          1 << 3},
            {nameof(ReplyPost.DisagreeNum),       1 << 4},
            {nameof(ReplyPost.Geolocation),       1 << 5}
        };

        private record UniqueSignature(uint Id, byte[] Md5)
        {
            public virtual bool Equals(UniqueSignature? other) =>
                ReferenceEquals(this, other) || (other != null && Id == other.Id && Md5.SequenceEqual(other.Md5));
            // https://stackoverflow.com/questions/7244699/gethashcode-on-byte-array/72925335#72925335
            public override int GetHashCode()
            {
                var hash = new HashCode();
                hash.Add(Id);
                hash.AddBytes(Md5);
                return hash.ToHashCode();
            }
        }
        private static readonly HashSet<UniqueSignature> SignaturesLock = new();
        private IEnumerable<UniqueSignature>? _savedSignatures;
        private readonly Fid _fid;

        public delegate ReplySaver New(ConcurrentDictionary<PostId, ReplyPost> posts, Fid fid);

        public ReplySaver(ILogger<ReplySaver> logger, ConcurrentDictionary<PostId, ReplyPost> posts, Fid fid)
            : base(logger, posts) => _fid = fid;

        public override SaverChangeSet<ReplyPost> SavePosts(TbmDbContext db)
        {
            var changeSet = SavePosts(db, r => r.Pid,
                PredicateBuilder.New<ReplyPost>(r => Posts.Keys.Contains(r.Pid)),
                r => new ReplyRevision {Time = r.UpdatedAt ?? r.CreatedAt, Pid = r.Pid});

            db.ReplyContents.AddRange(changeSet.NewlyAdded.Select(r => new ReplyContent {Pid = r.Pid, Content = r.Content}));

            // we have to get the value of field p.CreatedAt from existing posts that is fetched from db
            // since the value from Posts.Values.*.CreatedAt is 0 even after base.SavePosts() is invoked
            // because of Posts.Values.* is not the same instances as changeSet.Existing.*.After
            // so Posts.Values.* won't sync with new instances queried from db
            var signatures = changeSet.AllAfter.Where(r => r.SignatureId != null && r.Signature != null)
                .DistinctBy(r => r.SignatureId).Select(r => new ReplySignature
            {
                UserId = r.AuthorUid,
                SignatureId = (uint)r.SignatureId!,
                SignatureMd5 = MD5.HashData(r.Signature!),
                Signature = r.Signature!,
                // we have to generate the value of two field below in here, since its value will be set by TbmDbContext.SaveChanges() which is invoked after current method had returned
                FirstSeen = r.CreatedAt != 0 ? r.CreatedAt : (Time)DateTimeOffset.Now.ToUnixTimeSeconds(), // CreatedAt will be the default 0 value when the post is newly added
                LastSeen = (Time)DateTimeOffset.Now.ToUnixTimeSeconds()
            }).ToList();
            if (signatures.Any())
            {
                var uniqueSignatures = signatures.Select(s => new UniqueSignature(s.SignatureId, s.SignatureMd5)).ToList();
                var existingSignatures = (from s in db.ReplySignatures
                    where uniqueSignatures.Select(us => us.Id).Contains(s.SignatureId)
                          && uniqueSignatures.Select(us => us.Md5).Contains(s.SignatureMd5) select s).ToList();
                existingSignatures.ForEach(s => s.LastSeen = signatures.First(s2 => s2.SignatureId == s.SignatureId).LastSeen);
                lock (SignaturesLock)
                {
                    var newSignaturesExceptLocked = signatures.ExceptBy(existingSignatures.Select(s => s.SignatureId), s => s.SignatureId)
                        .ExceptBy(SignaturesLock, s => new(s.SignatureId, s.SignatureMd5)).ToList();
                    if (newSignaturesExceptLocked.Any())
                    {
                        _savedSignatures = newSignaturesExceptLocked.Select(s => new UniqueSignature(s.SignatureId, s.SignatureMd5));
                        SignaturesLock.UnionWith(_savedSignatures);
                        db.ReplySignatures.AddRange(newSignaturesExceptLocked);
                    }
                }
            }

            return changeSet;
        }

        public override void PostSaveHook()
        {
            if (_savedSignatures != null) lock (SignaturesLock) SignaturesLock.ExceptWith(_savedSignatures);
        }
    }
}
