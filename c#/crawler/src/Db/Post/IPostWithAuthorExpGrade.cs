// ReSharper disable UnusedMemberInSuper.Global
namespace tbm.Crawler.Db.Post;

public interface IPostWithAuthorExpGrade
{
    [NotMapped] public byte AuthorExpGrade { get; set; }
}
