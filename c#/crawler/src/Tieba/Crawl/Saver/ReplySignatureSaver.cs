using System.IO.Hashing;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public class ReplySignatureSaver(
    ILogger<ReplySignatureSaver> logger,
    SaverLocks<ReplySignatureSaver.UniqueSignature> locks)
{
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
                .GroupBy(r => new ReplySignatureProjection(r.SignatureId!.Value, r.Signature!))
                .Count()) (
                from r in repliesWithSignature
                group r by r.SignatureId into g
                where g.Count() > 1
                from r in g
                group r by new ReplySignatureProjection(r.SignatureId!.Value, r.Signature!) into g
                group g by g.Key.SignatureId into gg
                where gg.Count() > 1
                from g in gg
                select g)
            .ForEach(g => logger.LogWarning(
                "Multiple entities with different value of revisioning field sharing the same signature id {}: {}",
                g.Key.SignatureId, SharedHelper.UnescapedJsonSerialize(g)));

        var existingSignatures = (
            from s in db.ReplySignatures.AsTracking()
            where signatures.Select(s2 => s2.SignatureId).Contains(s.SignatureId)

                  // server side eval doesn't need ByteArrayEqualityComparer
                  && signatures.Select(s2 => s2.XxHash3).Contains(s.XxHash3)
            select s
        ).ToList();
        (from existing in existingSignatures
                join newInReply in signatures on existing.SignatureId equals newInReply.SignatureId
                select (existing, newInReply))
            .ForEach(t => t.existing.LastSeenAt = t.newInReply.LastSeenAt);

        var newSignatures = signatures
            .ExceptBy(existingSignatures.Select(s => s.SignatureId), s => s.SignatureId).ToList();
        var newlyLocked = locks.AcquireLocks(
            newSignatures.Select(s => new UniqueSignature(s.SignatureId, s.XxHash3)).ToList());
        db.ReplySignatures.AddRange(
            newSignatures.IntersectBy(newlyLocked, s => new(s.SignatureId, s.XxHash3)));
        return locks.Dispose;
    }

    private sealed record ReplySignatureProjection(uint SignatureId, byte[] Signature)
    {
        public bool Equals(ReplySignatureProjection? other) =>
            other != null
            && SignatureId == other.SignatureId
            && ByteArrayEqualityComparer.Instance.Equals(Signature, other.Signature);

        public override int GetHashCode()
        {
            var hash = default(HashCode);
            hash.Add(SignatureId);
            hash.AddBytes(Signature);
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
