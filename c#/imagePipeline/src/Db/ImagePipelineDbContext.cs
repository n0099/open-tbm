using Microsoft.EntityFrameworkCore.Infrastructure;

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
            new {e.ImageId, e.CenterPointX, e.CenterPointY, e.Width, e.Height, e.RotationDegrees, e.Recognizer});
        b.Entity<ImageOcrLine>().ToTable($"tbmc_image_ocr_line_{Script}");
        b.Entity<ImageHash>().ToTable("tbmc_image_hash");
        b.Entity<ImageMetadata>().ToTable("tbmc_image_metadata");
        b.Entity<ImageMetadata>().HasOne(e => e.DownloadedByteSize).WithOne()
            .HasForeignKey<ImageMetadata.ByteSize>(e => e.ImageId);
        b.Entity<ImageMetadata.ByteSize>().ToTable("tbmc_image_metadata_downloadedByteSize");
        b.Entity<ImageMetadata>().HasOne(e => e.EmbeddedMetadata).WithOne()
            .HasForeignKey<ImageMetadata.Embedded>(e => e.ImageId);
        b.Entity<ImageMetadata.Embedded>().ToTable("tbmc_image_metadata_embedded");
        b.Entity<ImageMetadata>().HasOne(e => e.JpgMetadata).WithOne()
            .HasForeignKey<ImageMetadata.Jpg>(e => e.ImageId);
        b.Entity<ImageMetadata.Jpg>().ToTable("tbmc_image_metadata_jpg");
    }
#pragma warning restore IDE0058 // Expression value is never used
}
