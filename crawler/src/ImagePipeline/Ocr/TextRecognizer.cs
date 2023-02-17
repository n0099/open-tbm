using Emgu.CV.CvEnum;
using Emgu.CV.Util;
using Emgu.CV;
using Emgu.CV.OCR;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class TextRecognizer
{
    private readonly Dictionary<string, string> _paddleOcrRecognitionEndpointsKeyByScript;
    private readonly Dictionary<string, Tesseract> _tesseractInstancesKeyByScript;
    private readonly float _tesseractConfidenceThreshold;

    public TextRecognizer(IConfiguration config)
    {
        var configSection = config.GetSection("ImageOcrPipeline");
        var paddleOcrServingEndpoint = configSection.GetValue("PaddleOcrServingEndpoint", "") ?? "";
        _paddleOcrRecognitionEndpointsKeyByScript = new()
        {
            {"zh-Hans", paddleOcrServingEndpoint + "/predict/ocr_rec"},
            {"zh-Hant", paddleOcrServingEndpoint + "/predict/ocr_rec_zh-Hant"},
            {"ja", paddleOcrServingEndpoint + "/predict/ocr_rec_ja"},
            {"en", paddleOcrServingEndpoint + "/predict/ocr_rec_en"},
        };

        var tesseractDataPath = configSection.GetValue("TesseractDataPath", "") ?? "";
        Tesseract CreateTesseract(string scripts) =>
            new(tesseractDataPath, scripts, OcrEngineMode.LstmOnly)
            { // https://pyimagesearch.com/2021/11/15/tesseract-page-segmentation-modes-psms-explained-how-to-improve-your-ocr-accuracy/
                PageSegMode = PageSegMode.SingleBlockVertText
            };
        _tesseractInstancesKeyByScript = new()
        {
            {"zh-Hans_vert", CreateTesseract("best/chi_sim_vert")},
            {"zh-Hant_vert", CreateTesseract("best/chi_tra_vert")},
            {"ja_vert", CreateTesseract("best/jpn_vert")}
        };
        _tesseractConfidenceThreshold = configSection.GetValue("TesseractConfidenceThreshold", 20f);
    }

    public record RecognizedResult(string TextBoxBoundary, string Script, string Text);

    public Task<IEnumerable<RecognizedResult>[]> RecognizeViaPaddleOcr
        (IEnumerable<TextBoxPreprocessor.ProcessedTextBox> textBoxes, CancellationToken stoppingToken = default)
    {
        var boxesKeyByBoundary = textBoxes.ToDictionary(b => b.TextBoxBoundary, b =>
        {
            using var mat = b.ProcessedTextBoxMat;
            return CvInvoke.Imencode(".png", mat);
        });
        return Task.WhenAll(_paddleOcrRecognitionEndpointsKeyByScript.Select(async pair =>
            (await PaddleOcrRequester.RequestForRecognition(pair.Value, boxesKeyByBoundary, stoppingToken))
            .SelectMany(results => results.Select(result =>
                new RecognizedResult(result.ImageId, pair.Key, result.Text)))));
    }

    public IEnumerable<RecognizedResult> RecognizeViaTesseract(TextBoxPreprocessor.ProcessedTextBox textBox)
    {
        using var mat = textBox.ProcessedTextBoxMat;
        CvInvoke.CvtColor(mat, mat, ColorConversion.Bgr2Gray);
        // https://docs.opencv.org/4.7.0/d7/d4d/tutorial_py_thresholding.html
        // http://www.fmwconcepts.com/imagemagick/threshold_comparison/index.php
        _ = CvInvoke.Threshold(mat, mat, 0, 255, ThresholdType.Binary | ThresholdType.Otsu);
        // CvInvoke.AdaptiveThreshold(threshedMat, threshedMat, 255, AdaptiveThresholdType.GaussianC, ThresholdType.Binary, 11, 3);
        // CvInvoke.MedianBlur(threshedMat, threshedMat, 1); // https://en.wikipedia.org/wiki/Salt-and-pepper_noise

        using var histogram = new Mat();
        using var threshedMatVector = new VectorOfMat(mat);
        CvInvoke.CalcHist(threshedMatVector, new[] { 0 }, null, histogram, new[] { 256 }, new[] { 0f, 256 }, false);
        // we don't need k-means clustering like https://stackoverflow.com/questions/50899692/most-dominant-color-in-rgb-image-opencv-numpy-python
        // since mat only composed of pure black and whites(aka 1bpp) after thresholding
        var dominantColor = histogram.Get<float>(0, 0) > histogram.Get<float>(255, 0) ? 0 : 255;
        // https://github.com/tesseract-ocr/tesseract/issues/427
        CvInvoke.CopyMakeBorder(mat, mat, 10, 10, 10, 10, BorderType.Constant, new(dominantColor, dominantColor, dominantColor));

        return _tesseractInstancesKeyByScript.Select(pair =>
        {
            var (script, tesseract) = pair;
            tesseract.SetImage(mat);
            if (tesseract.Recognize() != 0) return new(textBox.TextBoxBoundary, script, "");
            var text = tesseract.GetCharacters()
                .Where(c => c.Cost > _tesseractConfidenceThreshold)
                .Aggregate("", (acc, c) => acc + c.Text.Normalize(NormalizationForm.FormKC)); // https://unicode.org/reports/tr15/
            return new RecognizedResult(textBox.TextBoxBoundary, script, text);
        }).ToList(); // eager eval since mat is already disposed after return
    }
}
