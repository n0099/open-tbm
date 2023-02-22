using System.Drawing;
using Emgu.CV;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class TextRecognizer
{
    private readonly Dictionary<string, string> _paddleOcrRecognitionEndpointsKeyByScript;
    private readonly float _paddleOcrConfidenceThreshold;

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
        _paddleOcrConfidenceThreshold = configSection.GetValue("PaddleOcrConfidenceThreshold", 80f);
    }

    public record RecognizedResult(Rectangle TextBoxBoundary, string Script, string Text);

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
            .Where(result => result.Confidence > _paddleOcrConfidenceThreshold / 100)
            .Select(result => new RecognizedResult(result.TextBoxBoundary, pair.Key, result.Text))));
    }
}
