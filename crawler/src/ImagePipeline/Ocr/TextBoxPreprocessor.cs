using Emgu.CV;
using Emgu.CV.CvEnum;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class TextBoxPreprocessor
{
    public record ProcessedTextBox(string TextBoxBoundary, Mat ProcessedTextBoxMat, float RotationDegrees);

    public record ImageAndProcessedTextBoxes(string ImageId, List<ProcessedTextBox> ProcessedTextBoxes);

    public static ImageAndProcessedTextBoxes ProcessTextBoxes(PaddleOcrRequester.DetectionResult detectionResult)
    {
        using var imageMat = new Mat();
        CvInvoke.Imdecode(detectionResult.ImageBytes, ImreadModes.Unchanged, imageMat);
        return new(detectionResult.ImageId, detectionResult.TextBoxes
            .Select(textBoxAndDegrees =>
            {
                var (textBox, degrees) = textBoxAndDegrees;
                var circumscribed = textBox.ToCircumscribedRectangle();
                var processedMat = new Mat(imageMat, circumscribed); // crop by circumscribed rectangle
                if (degrees != 0) processedMat.Rotate(degrees);
                var rectangleBoundary = $"{circumscribed.Width}x{circumscribed.Height}@{circumscribed.X},{circumscribed.Y}";
                return new ProcessedTextBox(rectangleBoundary, processedMat, degrees);
            })
            .ToList()); // eager eval since imageMat is already disposed after return
    }
}
