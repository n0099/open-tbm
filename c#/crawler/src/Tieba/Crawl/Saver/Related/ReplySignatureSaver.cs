using System.IO.Hashing;

namespace tbm.Crawler.Tieba.Crawl.Saver.Related;

public class ReplySignatureSaver(
    ILogger<ReplySignatureSaver> logger,
    SaverLocks<ReplySignatureSaver.UniqueSignature>.New saverLocksFactory)
{
    private static readonly HashSet<UniqueSignature> GlobalLockedSignatures = [];
    private readonly Lazy<SaverLocks<UniqueSignature>> _saverLocks =
        new(() => saverLocksFactory(GlobalLockedSignatures));

    public Action Save(CrawlerDbContext db, IEnumerable<ReplyPost> replies)
    {
        SharedHelper.GetNowTimestamp(out var now);
        var repliesWithSignature = replies
            .Where(r => r is {SignatureId: not null, Signature: not null}).ToList();
        var signatures = repliesWithSignature

            // only takes the first of multiple signature sharing the same id
            .DistinctBy(r => r.SignatureId)
            .Select(r => new ReplySignature
            {
                UserId = r.AuthorUid,
                SignatureId = (uint)r.SignatureId!,
                XxHash3 = XxHash3.Hash(r.Signature!),
                ProtoBufBytes = r.Signature!,
                FirstSeenAt = now,
                LastSeenAt = now
            }).ToList();
        if (signatures.Count == 0) return () => { };
        if (signatures.Count != repliesWithSignature
                .GroupBy(r => (r.SignatureId!.Value, r.Signature!),
                    comparer: SignatureIdAndValueEqualityComparer.Instance)
                .Count())
            Helper.LogDifferentValuesSharingTheSameKeyInEntities(logger,
                repliesWithSignature,
                nameof(ReplyPost.SignatureId),
                r => r.SignatureId,
                r => r.Signature,
                SignatureIdAndValueEqualityComparer.Instance);

        var existingSignatures = db.ReplySignatures.AsTracking().FilterByItems(
                signatures, (existing, newOrExisting) =>
                    existing.SignatureId == newOrExisting.SignatureId
                    && existing.XxHash3 == newOrExisting.XxHash3)
            .ToList();
        (from existing in existingSignatures
                join newInReply in signatures on existing.SignatureId equals newInReply.SignatureId
                select (existing, newInReply))
            .ForEach(t => t.existing.LastSeenAt = t.newInReply.LastSeenAt);

        var newSignatures = signatures
            .ExceptBy(existingSignatures.Select(s => s.SignatureId), s => s.SignatureId).ToList();
        var newlyLocked = _saverLocks.Value.Acquire(
            newSignatures.Select(s => new UniqueSignature(s.SignatureId, s.XxHash3)).ToList());
        db.ReplySignatures.AddRange(
            newSignatures.IntersectBy(newlyLocked, s => new(s.SignatureId, s.XxHash3)));
        return _saverLocks.Value.Dispose;
    }

    [SuppressMessage("Class Design", "AV1000:Type name contains the word 'and', which suggests it has multiple purposes")]
    private sealed class SignatureIdAndValueEqualityComparer : EqualityComparer<(uint? SignatureId, byte[]? Signature)>
    {
        public static SignatureIdAndValueEqualityComparer Instance { get; } = new();

        public override bool Equals((uint? SignatureId, byte[]? Signature) x, (uint? SignatureId, byte[]? Signature) y) =>
            x == y ||
            (x.SignatureId == y.SignatureId
             && ByteArrayEqualityComparer.Instance.Equals(x.Signature, y.Signature));

        public override int GetHashCode((uint? SignatureId, byte[]? Signature) obj)
        {
            var hash = default(HashCode);
            hash.Add(obj.SignatureId);
            hash.AddBytes(obj.Signature);
            return hash.ToHashCode();
        }
    }

    public sealed record UniqueSignature(uint Id, byte[] XxHash3)
    {
        public bool Equals(UniqueSignature? other) =>
            other != null
            && Id == other.Id
            && ByteArrayEqualityComparer.Instance.Equals(XxHash3, other.XxHash3);

        public override int GetHashCode()
        {
            var hash = default(HashCode);
            hash.Add(Id);
            hash.AddBytes(XxHash3);
            return hash.ToHashCode();
        }
    }
}
