using System.Linq.Expressions;
using Microsoft.EntityFrameworkCore.Infrastructure;
using static tbm.ImagePipeline.Db.ImageMetadata;

namespace tbm.ImagePipeline.Db;

public class ImagePipelineDbContext : TbmDbContext<ImagePipelineDbContext.ModelWithScriptCacheKeyFactory>
{
    public class ModelWithScriptCacheKeyFactory : IModelCacheKeyFactory
    { // https://stackoverflow.com/questions/51864015/entity-framework-map-model-class-to-table-at-run-time/51899590#51899590
        // https://docs.microsoft.com/en-us/ef/core/modeling/dynamic-model
        public object Create(DbContext context, bool designTime) =>
            context is ImagePipelineDbContext dbContext
                ? (context.GetType(), dbContext.Script, designTime)
                : context.GetType();
    }

    private string Script { get; }
    public DbSet<TiebaImage> Images => Set<TiebaImage>();
    public DbSet<ImageOcrBox> ImageOcrBoxes => Set<ImageOcrBox>();
    public DbSet<ImageOcrLine> ImageOcrLines => Set<ImageOcrLine>();
    public DbSet<ImageHash> ImageHashes => Set<ImageHash>();
    public DbSet<ImageMetadata> ImageMetadata => Set<ImageMetadata>();

    public delegate ImagePipelineDbContext New(string script);

    public ImagePipelineDbContext(IConfiguration config, string script) : base(config) => Script = script;

#pragma warning disable IDE0058 // Expression value is never used
    protected override void OnModelCreating(ModelBuilder b)
    {
        b.Entity<TiebaImage>().ToTable("tbmc_image");
        b.Entity<ImageOcrBox>().ToTable($"tbmc_image_ocr_box_{Script}").HasKey(e =>
            new {e.ImageId, e.FrameIndex, e.CenterPointX, e.CenterPointY, e.Width, e.Height, e.RotationDegrees, e.Recognizer});
        b.Entity<ImageOcrLine>().ToTable($"tbmc_image_ocr_line_{Script}").HasKey(e => new {e.ImageId, e.FrameIndex});
        b.Entity<ImageHash>().ToTable("tbmc_image_hash").HasKey(e => new {e.ImageId, e.FrameIndex});
        b.Entity<ImageMetadata>().ToTable("tbmc_image_metadata");

        void SplitImageMetadata<TEntity, TRelatedEntity>(Expression<Func<TEntity, TRelatedEntity?>> keySelector, string tableNameSuffix)
            where TEntity : class
            where TRelatedEntity : class, IImageMetadata
        {
            b.Entity<TEntity>().HasOne(keySelector).WithOne().HasForeignKey<TRelatedEntity>(e => e.ImageId);
            b.Entity<TRelatedEntity>().ToTable("tbmc_image_metadata_" + tableNameSuffix);
        }
        SplitImageMetadata<ImageMetadata, ByteSize>(e => e.DownloadedByteSize, "downloadedByteSize");
        SplitImageMetadata<ImageMetadata, Other>(e => e.EmbeddedOther, "embedded");
        SplitImageMetadata<ImageMetadata, Exif>(e => e.EmbeddedExif, "embedded_exif");
        SplitImageMetadata<ImageMetadata, Icc>(e => e.EmbeddedIcc, "embedded_icc");
        SplitImageMetadata<ImageMetadata, Jpg>(e => e.JpgMetadata, "jpg");
        SplitImageMetadata<ImageMetadata, Png>(e => e.PngMetadata, "png");
        SplitImageMetadata<ImageMetadata, Gif>(e => e.GifMetadata, "gif");
        SplitImageMetadata<ImageMetadata, Bmp>(e => e.BmpMetadata, "bmp");
    }
#pragma warning restore IDE0058 // Expression value is never used
}
