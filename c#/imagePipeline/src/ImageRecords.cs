namespace tbm.ImagePipeline;

public record ImageKey(ImageId ImageId, uint FrameIndex);

public record ImageKeyWithMatrix(ImageId ImageId, uint FrameIndex, Mat Matrix);

public record ImageWithBytes(ImageInReply ImageInReply, byte[] Bytes);
