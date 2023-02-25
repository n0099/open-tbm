using Emgu.CV;
using Emgu.CV.CvEnum;
using Emgu.CV.OCR;
using Emgu.CV.Util;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class TesseractRecognizer
{
    private readonly (Dictionary<string, Tesseract> Horizontal, Dictionary<string, Tesseract> Vertical) _tesseractInstancesKeyByScript;
    private readonly float _tesseractConfidenceThreshold;
    private readonly float _aspectRatioThresholdToUseTesseract;

    public TesseractRecognizer(IConfiguration config)
    {
        var configSection = config.GetSection("ImageOcrPipeline");
        var tesseractDataPath = configSection.GetValue("TesseractDataPath", "") ?? "";
        Tesseract CreateTesseract(string scripts) =>
            new(tesseractDataPath, scripts, OcrEngineMode.LstmOnly)
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
        _tesseractConfidenceThreshold = configSection.GetValue("TesseractConfidenceThreshold", 20f);
        _aspectRatioThresholdToUseTesseract = configSection.GetValue("AspectRatioThresholdToUseTesseract", 0.8f);
    }

    public record PreprocessedTextBox(string ImageId, string Script, PaddleOcrResponse.TextBox TextBox, Mat PreprocessedTextBoxMat);

    public static List<PreprocessedTextBox> PreprocessTextBoxes
        (string imageId, byte[] imageBytes, string script, IEnumerable<PaddleOcrResponse.TextBox> textBoxes)
    {
        using var mat = new Mat();
        CvInvoke.Imdecode(imageBytes, ImreadModes.Unchanged, mat);
        return textBoxes
            .Select(textBox =>
            {
                var degrees = textBox.GetRotationDegrees();
                var preprocessedMat = new Mat(mat, textBox.ToCircumscribedRectangle()); // crop by circumscribed rectangle
                if (degrees != 0) preprocessedMat.Rotate(degrees);
                return new PreprocessedTextBox(imageId, script, textBox, preprocessedMat);
            })
            .ToList(); // eager eval since mat is already disposed after return
    }

    public record RecognitionResult(string ImageId, string Script, bool IsVertical, PaddleOcrResponse.TextBox TextBox, string Text, ushort Confidence);

    public IEnumerable<RecognitionResult> RecognizePreprocessedTextBox(PreprocessedTextBox textBox)
    {
        using var mat = textBox.PreprocessedTextBoxMat;
        CvInvoke.CvtColor(mat, mat, ColorConversion.Bgr2Gray);
        // https://docs.opencv.org/4.7.0/d7/d4d/tutorial_py_thresholding.html
        // http://www.fmwconcepts.com/imagemagick/threshold_comparison/index.php
        _ = CvInvoke.Threshold(mat, mat, 0, 255, ThresholdType.Binary | ThresholdType.Otsu);
        // CvInvoke.AdaptiveThreshold(threshedMat, threshedMat, 255, AdaptiveThresholdType.GaussianC, ThresholdType.Binary, 11, 3);
        // CvInvoke.MedianBlur(threshedMat, threshedMat, 1); // https://en.wikipedia.org/wiki/Salt-and-pepper_noise

        using var histogram = new Mat();
        using var threshedMatVector = new VectorOfMat(mat);
        CvInvoke.CalcHist(threshedMatVector, new[] {0}, null, histogram, new[] {256}, new[] {0f, 256}, false);
        // we don't need k-means clustering like https://stackoverflow.com/questions/50899692/most-dominant-color-in-rgb-image-opencv-numpy-python
        // since mat only composed of pure black and whites(aka 1bpp) after thresholding
        var dominantColor = histogram.Get<float>(0, 0) > histogram.Get<float>(255, 0) ? 0 : 255;
        // https://github.com/tesseract-ocr/tesseract/issues/427
        CvInvoke.CopyMakeBorder(mat, mat, 10, 10, 10, 10, BorderType.Constant, new(dominantColor, dominantColor, dominantColor));

        var isVertical = (float)textBox.PreprocessedTextBoxMat.Width / textBox.PreprocessedTextBoxMat.Height < _aspectRatioThresholdToUseTesseract;
        return (isVertical ? _tesseractInstancesKeyByScript.Vertical : _tesseractInstancesKeyByScript.Horizontal)
            .Where(pair => pair.Key == textBox.Script)
            .Select(pair =>
            {
                var (script, tesseract) = pair;
                tesseract.SetImage(mat);
                if (tesseract.Recognize() != 0) return null;
                var chars = tesseract.GetCharacters();
                var text = string.Join("", chars
                    .Where(c => c.Cost > _tesseractConfidenceThreshold)
                    .Select(c => c.Text)).Trim();
                if (!chars.Any() || text == "") return null;
                var averageConfidence = (ushort)Math.Round(chars.Select(c => c.Cost).Average(), 0);
                return new RecognitionResult(textBox.ImageId, script, isVertical, textBox.TextBox, text, averageConfidence);
            })
            .OfType<RecognitionResult>().ToList(); // eager eval since mat is already disposed after return
    }
}
