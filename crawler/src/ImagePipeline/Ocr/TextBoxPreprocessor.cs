using System.Drawing;
using Emgu.CV;
using Emgu.CV.CvEnum;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class TextBoxPreprocessor
{
    public record ProcessedTextBox(Rectangle TextBoxBoundary, Mat ProcessedTextBoxMat, float RotationDegrees);

    public static (string ImageId, List<ProcessedTextBox> ProcessedTextBoxes)
        ProcessTextBoxes(PaddleOcrRequester.DetectionResult detectionResult)
    {
        using var mat = new Mat();
        CvInvoke.Imdecode(detectionResult.ImageBytes, ImreadModes.Unchanged, mat);
        return (detectionResult.ImageId, detectionResult.TextBoxes
            .Select(textBoxAndDegrees =>
            {
                var (textBox, degrees) = textBoxAndDegrees;
                var circumscribed = textBox.ToCircumscribedRectangle();
                var processedMat = new Mat(mat, circumscribed); // crop by circumscribed rectangle
                if (degrees != 0) processedMat.Rotate(degrees);
                return new ProcessedTextBox(circumscribed, processedMat, degrees);
            })
            .ToList()); // eager eval since imageMat is already disposed after return
    }
}
