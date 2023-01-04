namespace tbm.Crawler.Db.Post
{
    public interface IPostWithAuthorExpGrade
    {
        [NotMapped] public ushort AuthorExpGrade { get; set; }
    }
}
