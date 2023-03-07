using Emgu.CV;
using Emgu.CV.CvEnum;
using Emgu.CV.OCR;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class TesseractRecognizer : IDisposable
{
    private readonly (Dictionary<string, Tesseract> Horizontal, Dictionary<string, Tesseract> Vertical) _tesseractInstancesKeyByScript;
    private readonly int _confidenceThreshold;
    private readonly float _aspectRatioThresholdToConsiderAsVertical;

    public TesseractRecognizer(IConfiguration config)
    {
        var configSection = config.GetSection("ImageOcrPipeline").GetSection("Tesseract");
        var dataPath = configSection.GetValue("DataPath", "") ?? "";
        Tesseract CreateTesseract(string scripts) =>
            new(dataPath, scripts, OcrEngineMode.LstmOnly)
            { // https://pyimagesearch.com/2021/11/15/tesseract-page-segmentation-modes-psms-explained-how-to-improve-your-ocr-accuracy/
                PageSegMode = PageSegMode.SingleBlockVertText
            };
        _tesseractInstancesKeyByScript.Horizontal = new()
        {
            {"zh-Hans", CreateTesseract("best/chi_sim+best/eng")},
            {"zh-Hant", CreateTesseract("best/chi_tra+best/eng")},
            {"ja", CreateTesseract("best/jpn")}, // literal latin letters in japanese is replaced by katakana
            {"en", CreateTesseract("best/eng")}
        };
        _tesseractInstancesKeyByScript.Vertical = new()
        {
            {"zh-Hans", CreateTesseract("best/chi_sim_vert")},
            {"zh-Hant", CreateTesseract("best/chi_tra_vert")},
            {"ja", CreateTesseract("best/jpn_vert")}
        };
        _confidenceThreshold = configSection.GetValue("ConfidenceThreshold", 20);
        _aspectRatioThresholdToConsiderAsVertical = configSection.GetValue("AspectRatioThresholdToConsiderAsVertical", 0.8f);
    }

    public void Dispose() => _tesseractInstancesKeyByScript.Horizontal
        .Concat(_tesseractInstancesKeyByScript.Vertical)
        .ForEach(pair => pair.Value.Dispose());

    public record PreprocessedTextBox(string ImageId, bool IsUnrecognized, PaddleOcrResponse.TextBox TextBox, Mat PreprocessedTextBoxMat);

    public static List<PreprocessedTextBox> PreprocessTextBoxes
        (string imageId, byte[] imageBytes, IEnumerable<(bool IsUnrecognized, PaddleOcrResponse.TextBox)> textBoxes)
    {
        using var originalImageMat = new Mat();
        CvInvoke.Imdecode(imageBytes, ImreadModes.Unchanged, originalImageMat);
        return textBoxes
            .Select(t =>
            {
                var (isUnrecognized, textBox) = t;
                var degrees = textBox.GetRotationDegrees();
                var mat = new Mat(originalImageMat, textBox.ToCircumscribedRectangle()); // crop by circumscribed rectangle

                CvInvoke.CvtColor(mat, mat, ColorConversion.Bgr2Gray);
                // https://docs.opencv.org/4.7.0/d7/d4d/tutorial_py_thresholding.html
                // http://www.fmwconcepts.com/imagemagick/threshold_comparison/index.php
                _ = CvInvoke.Threshold(mat, mat, 0, 255, ThresholdType.Binary | ThresholdType.Otsu);

                if (degrees != 0) mat.Rotate(degrees);

                // https://github.com/tesseract-ocr/tesseract/issues/427
                CvInvoke.CopyMakeBorder(mat, mat, 10, 10, 10, 10, BorderType.Constant, new(0, 0, 0));

                return new PreprocessedTextBox(imageId, isUnrecognized, textBox, mat);
            })
            .ToList(); // eager eval since mat is already disposed after return
    }

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
                tesseract.SetImage(mat);
                if (tesseract.Recognize() != 0) return null;
                var chars = tesseract.GetCharacters();
                var text = string.Join("", chars
                    .Where(c => c.Cost > _confidenceThreshold)
                    .Select(c => c.Text)).Trim();
                if (!chars.Any() || text == "") return null;
                var averageConfidence = chars.Select(c => c.Cost).Average().RoundToUshort();
                return new TesseractRecognitionResult(imageId, script, isVertical, isUnrecognized, box, text, averageConfidence);
            })
            .OfType<TesseractRecognitionResult>().ToList(); // eager eval since mat is already disposed after return
    }
}
