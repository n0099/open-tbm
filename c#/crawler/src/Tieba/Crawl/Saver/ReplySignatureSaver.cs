using System.IO.Hashing;

namespace tbm.Crawler.Tieba.Crawl.Saver;

public class ReplySignatureSaver(SaverLocks<ReplySignatureSaver.UniqueSignature> locks)
{
    public Action SaveReplySignatures(CrawlerDbContext db, IEnumerable<ReplyPost> replies)
    {
        SharedHelper.GetNowTimestamp(out var now);
        var signatures = replies
            .Where(r => r is {SignatureId: not null, Signature: not null})
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
        return locks.ReleaseLocalLocked;
    }

    public sealed record UniqueSignature(uint Id, byte[] XxHash3)
    {
        public bool Equals(UniqueSignature? other) =>
            other != null && Id == other.Id && ByteArrayEqualityComparer.Instance.Equals(XxHash3, other.XxHash3);

        public override int GetHashCode()
        {
            var hash = default(HashCode);
            hash.Add(Id);
            hash.AddBytes(XxHash3);
            return hash.ToHashCode();
        }
    }
}
