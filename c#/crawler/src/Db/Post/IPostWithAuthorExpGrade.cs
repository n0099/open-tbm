// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Post;

public interface IPostWithAuthorExpGrade : IPost
{
    [NotMapped] public byte AuthorExpGrade { get; set; }
}
