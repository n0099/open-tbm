// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Post;

public abstract class PostWithAuthorExpGrade : BasePost
{
    [NotMapped] public byte AuthorExpGrade { get; set; }
}
