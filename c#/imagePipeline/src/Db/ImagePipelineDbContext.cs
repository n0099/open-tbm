using Microsoft.EntityFrameworkCore.Infrastructure;
using Npgsql.EntityFrameworkCore.PostgreSQL.Infrastructure;
using static tbm.ImagePipeline.Db.ImageMetadata;

namespace tbm.ImagePipeline.Db;

public class ImagePipelineDbContext(ILogger<ImagePipelineDbContext> logger, Fid fid = 0, string script = "")
    : TbmDbContext<ImagePipelineDbContext.ModelCacheKeyFactory>(logger)
{
    public delegate ImagePipelineDbContext NewDefault();
    public delegate ImagePipelineDbContext New(Fid fid, string script);

    public DbSet<ForumScript> ForumScripts => Set<ForumScript>();
    public DbSet<ImageOcrBox> ImageOcrBoxes => Set<ImageOcrBox>();
    public DbSet<ImageOcrLine> ImageOcrLines => Set<ImageOcrLine>();
    public DbSet<ImageQrCode> ImageQrCodes => Set<ImageQrCode>();
    public DbSet<ImageHash> ImageHashes => Set<ImageHash>();
    public DbSet<ImageMetadata> ImageMetadata => Set<ImageMetadata>();
    public DbSet<ImageFailed> ImageFailed => Set<ImageFailed>();
    private Fid Fid { get; } = fid;
    private string Script { get; } = script;

    protected override void OnConfiguringNpgsql(NpgsqlDbContextOptionsBuilder builder) => builder.UseNetTopologySuite();

    [SuppressMessage("Style", "IDE0058:Expression value is never used")]
    protected override void OnModelCreating(ModelBuilder b)
    {
        base.OnModelCreating(b);
        OnModelCreatingWithFid(b, Fid);
        b.Entity<ImageHash>().ToTable("tbmi_hash").HasKey(e => new {e.ImageId, e.FrameIndex});
        b.Entity<ImageOcrLine>().ToTable($"tbmi_ocr_line_{Script}_f{Fid}").HasKey(e => new {e.ImageId, e.FrameIndex});
        b.Entity<ImageOcrBox>().ToTable($"tbmi_ocr_box_{Script}").HasKey(e =>
            new {e.ImageId, e.FrameIndex, e.CenterPointX, e.CenterPointY, e.Width, e.Height, e.RotationDegrees, e.Recognizer});
        b.Entity<ImageQrCode>().ToTable("tbmi_qrCode").HasKey(e =>
            new {e.ImageId, e.FrameIndex, e.Point1X, e.Point1Y, e.Point2X, e.Point2Y, e.Point3X, e.Point3Y, e.Point4X, e.Point4Y});
        b.Entity<ImageQrCode>().Property(e => e.Text).HasConversion<byte[]>();
        b.Entity<ImageMetadata>().ToTable("tbmi_metadata");
        b.Entity<ImageFailed>().ToTable("tbmi_failed");
        b.Entity<ForumScript>().ToTable("tbmi_forum_script").HasKey(e => new {e.Fid, e.Script});

        void SplitImageMetadata<TRelatedEntity>
            (Expression<Func<ImageMetadata, TRelatedEntity?>> keySelector, string tableNameSuffix)
            where TRelatedEntity : EntityWithImageId
        {
            b.Entity<ImageMetadata>().HasOne(keySelector).WithOne().HasForeignKey<TRelatedEntity>(e => e.ImageId);
            b.Entity<TRelatedEntity>().ToTable($"tbmi_metadata_{tableNameSuffix}");
        }
        SplitImageMetadata(e => e.EmbeddedExif, "embedded_exif");
        b.Entity<Exif>().HasMany(e => e.TagNames).WithOne().HasForeignKey(e => e.ImageId);
        b.Entity<Exif>().Property(e => e.UserComment).HasConversion<byte[]>();
        b.Entity<Exif>().Property(e => e.XpAuthor).HasConversion<byte[]>();
        b.Entity<Exif.TagName>().ToTable("tbmi_metadata_embedded_exif_tagName").HasKey(e => new {e.ImageId, e.Name});

        SplitImageMetadata(e => e.EmbeddedIcc, "embedded_icc");
        SplitImageMetadata(e => e.EmbeddedIptc, "embedded_iptc");
        SplitImageMetadata(e => e.EmbeddedXmp, "embedded_xmp");
        SplitImageMetadata(e => e.DownloadedByteSize, "downloadedByteSize");
        SplitImageMetadata(e => e.JpgMetadata, "jpg");
        SplitImageMetadata(e => e.PngMetadata, "png");
        SplitImageMetadata(e => e.GifMetadata, "gif");
        SplitImageMetadata(e => e.BmpMetadata, "bmp");
    }

    public class ModelCacheKeyFactory : IModelCacheKeyFactory
    { // https://stackoverflow.com/questions/51864015/entity-framework-map-model-class-to-table-at-run-time/51899590#51899590
        // https://docs.microsoft.com/en-us/ef/core/modeling/dynamic-model
        public object Create(DbContext context, bool designTime) =>
            context is ImagePipelineDbContext dbContext
                ? (context.GetType(), dbContext.Fid, dbContext.Script, designTime)
                : context.GetType();
    }
}
