namespace tbm.ImagePipeline.Db;

public abstract class ImageWithFrameIndex : EntityWithImageId
{
    public uint FrameIndex { get; set; }
}
