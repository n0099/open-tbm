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

        var uniqueSignatures = signatures
            .ConvertAll(s => new UniqueSignature(s.SignatureId, s.XxHash3));
        var existingSignatures = (
            from s in db.ReplySignatures.AsTracking()
            where uniqueSignatures.Select(us => us.Id).Contains(s.SignatureId)

                  // server side eval doesn't need ByteArrayEqualityComparer
                  && uniqueSignatures.Select(us => us.XxHash3).Contains(s.XxHash3)
            select s
        ).ToList();
        (from existing in existingSignatures
                join newInReply in signatures on existing.SignatureId equals newInReply.SignatureId
                select (existing, newInReply))
            .ForEach(t => t.existing.LastSeenAt = t.newInReply.LastSeenAt);

        locks.AcquireLocksThen(db.ReplySignatures.AddRange,
            alreadyLocked => signatures
                .ExceptBy(existingSignatures.Select(s => s.SignatureId), s => s.SignatureId)
                .ExceptBy(alreadyLocked, s => new(s.SignatureId, s.XxHash3))
                .ToList(),
            newlyLocked => newlyLocked
                .Select(s => new UniqueSignature(s.SignatureId, s.XxHash3)));
        return locks.ReleaseLocalLocked;
    }

    public sealed record UniqueSignature(uint Id, byte[] XxHash3)
    {
        public bool Equals(UniqueSignature? other) =>
            other != null && Id == other.Id && new ByteArrayEqualityComparer().Equals(XxHash3, other.XxHash3);

        public override int GetHashCode()
        {
            var hash = default(HashCode);
            hash.Add(Id);
            hash.AddBytes(XxHash3);
            return hash.ToHashCode();
        }
    }
}
