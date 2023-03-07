using OpenCvSharp;
using OpenCvSharp.Text;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class TesseractRecognizer : IDisposable
{
    private readonly (Dictionary<string, OCRTesseract> Horizontal, Dictionary<string, OCRTesseract> Vertical) _tesseractInstancesKeyByScript;
    private readonly int _confidenceThreshold;
    private readonly float _aspectRatioThresholdToConsiderAsVertical;

    public TesseractRecognizer(IConfiguration config)
    {
        var configSection = config.GetSection("ImageOcrPipeline").GetSection("Tesseract");
        var dataPath = configSection.GetValue("DataPath", "") ?? "";
        // https://pyimagesearch.com/2021/11/15/tesseract-page-segmentation-modes-psms-explained-how-to-improve-your-ocr-accuracy/
        OCRTesseract CreateTesseract(string scripts, int psmode) => OCRTesseract.Create(dataPath, scripts, psmode: psmode);
        _tesseractInstancesKeyByScript.Horizontal = new()
        {
            {"zh-Hans", CreateTesseract("best/chi_sim+best/eng", 7)},
            {"zh-Hant", CreateTesseract("best/chi_tra+best/eng", 7)},
            {"ja", CreateTesseract("best/jpn", 7)}, // literal latin letters in japanese is replaced by katakana
            {"en", CreateTesseract("best/eng", 7)}
        };
        _tesseractInstancesKeyByScript.Vertical = new()
        {
            {"zh-Hans", CreateTesseract("best/chi_sim_vert", 5)},
            {"zh-Hant", CreateTesseract("best/chi_tra_vert", 5)},
            {"ja", CreateTesseract("best/jpn_vert", 5)}
        };
        _confidenceThreshold = configSection.GetValue("ConfidenceThreshold", 20);
        _aspectRatioThresholdToConsiderAsVertical = configSection.GetValue("AspectRatioThresholdToConsiderAsVertical", 0.8f);
    }

    public void Dispose() => _tesseractInstancesKeyByScript.Horizontal
        .Concat(_tesseractInstancesKeyByScript.Vertical)
        .ForEach(pair => pair.Value.Dispose());

    public record PreprocessedTextBox(string ImageId, bool IsUnrecognized, PaddleOcrResponse.TextBox TextBox, Mat PreprocessedTextBoxMat);

    public static List<PreprocessedTextBox> PreprocessTextBoxes
        (string imageId, Mat imageMat, IEnumerable<(bool IsUnrecognized, PaddleOcrResponse.TextBox)> textBoxes) => textBoxes
        .Select(t =>
        {
            var (isUnrecognized, textBox) = t;
            var degrees = textBox.GetRotationDegrees();
            var circumscribed = textBox.ToCircumscribedRectangle(); // crop by circumscribed rectangle
            var mat = new Mat(imageMat, Rect.FromLTRB(circumscribed.Left, circumscribed.Top, circumscribed.Right, circumscribed.Bottom));

            Cv2.CvtColor(mat, mat, ColorConversionCodes.BGR2GRAY);
            // https://docs.opencv.org/4.7.0/d7/d4d/tutorial_py_thresholding.html
            // http://www.fmwconcepts.com/imagemagick/threshold_comparison/index.php
            _ = Cv2.Threshold(mat, mat, 0, 255, ThresholdTypes.Otsu | ThresholdTypes.Binary);

            if (degrees != 0) mat.Rotate(degrees);

            // https://github.com/tesseract-ocr/tesseract/issues/427
            Cv2.CopyMakeBorder(mat, mat, 10, 10, 10, 10, BorderTypes.Constant, new(0, 0, 0));

            return new PreprocessedTextBox(imageId, isUnrecognized, textBox, mat);
        })
        .ToList(); // eager eval since mat is already disposed after return

    public IEnumerable<TesseractRecognitionResult> RecognizePreprocessedTextBox(string script, PreprocessedTextBox textBox)
    {
        var (imageId, isUnrecognized, box, preprocessedTextBoxMat) = textBox;
        using var mat = preprocessedTextBoxMat;
        var isVertical = (float)mat.Width / mat.Height < _aspectRatioThresholdToConsiderAsVertical;
        return (isVertical ? _tesseractInstancesKeyByScript.Vertical : _tesseractInstancesKeyByScript.Horizontal)
            .Where(pair => pair.Key == script)
            .Select(pair =>
            {
                var tesseract = pair.Value;
                tesseract.Run(mat, out _, out var rects, out var texts, out var confidences);
                var components = rects.Zip(texts, confidences)
                    .Select(t => (Rect: t.First, Text: t.Second, Confidence: t.Third)).ToList();
                var text = string.Join("", components
                    .Where(t => t.Confidence > _confidenceThreshold)
                    .Select(t => t.Text)).Trim();
                if (!components.Any() || text == "") return null;
                var averageConfidence = components.Select(c => c.Confidence).Average().RoundToUshort();
                return new TesseractRecognitionResult(imageId, script, isVertical, isUnrecognized, box, text, averageConfidence);
            })
            .OfType<TesseractRecognitionResult>().ToList(); // eager eval since mat is already disposed after return
    }
}
