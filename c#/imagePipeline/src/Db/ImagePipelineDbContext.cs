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
    public DbSet<TiebaImageOcrBoxes> ImageOcrBoxes => Set<TiebaImageOcrBoxes>();
    public DbSet<TiebaImageOcrLines> ImageOcrLines => Set<TiebaImageOcrLines>();

    public delegate ImagePipelineDbContext New(string script);

    public ImagePipelineDbContext(IConfiguration config, string script) : base(config) => Script = script;

#pragma warning disable IDE0058 // Expression value is never used
    protected override void OnModelCreating(ModelBuilder b)
    {
        b.Entity<TiebaImage>().ToTable("tbmc_image");
        b.Entity<TiebaImageOcrBoxes>().ToTable("tbmc_image_ocr_boxes").HasKey(e =>
            new {e.ImageId, e.CenterPointX, e.CenterPointY, e.Width, e.Height, e.RotationDegrees, e.Recognizer, e.Script});
        b.Entity<TiebaImageOcrLines>().ToTable("tbmc_image_ocr_lines").HasKey(e => new {e.ImageId, e.Script});
    }
#pragma warning restore IDE0058 // Expression value is never used
}
