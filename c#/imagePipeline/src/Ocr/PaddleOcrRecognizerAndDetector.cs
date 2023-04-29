using OpenCvSharp;
using Sdcb.PaddleInference;
using Sdcb.PaddleOCR;
using Sdcb.PaddleOCR.Models.Online;

namespace tbm.ImagePipeline.Ocr;

public class PaddleOcrRecognizerAndDetector : IDisposable
{
    private readonly string _script;
    private PaddleOcrAll? _ocr;

    public delegate PaddleOcrRecognizerAndDetector New(string script);

    public PaddleOcrRecognizerAndDetector(IConfiguration config, string script)
    {
        _script = script;
        Settings.GlobalModelDirectory =
            config.GetSection("ImageOcrPipeline").GetSection("PaddleOcr")
                .GetValue("ModelPath", "./PaddleOcrModels")
            ?? "./PaddleOcrModels";
    }

    public void Dispose() => _ocr?.Dispose();

    private static Func<CancellationToken, Task<PaddleOcrAll>>
        GetPaddleOcrFactory(OnlineFullModels model) => async stoppingToken =>
        new(await model.DownloadAsync(stoppingToken), PaddleDevice.Mkldnn(1)) // https://github.com/sdcb/PaddleSharp/pull/46
        {
            AllowRotateDetection = true,
            Enable180Classification = true
        };

    public async Task Initialize(CancellationToken stoppingToken = default) =>
        _ocr ??= await (_script switch
        {
            "zh-Hans" => GetPaddleOcrFactory(OnlineFullModels.ChineseV3),
            "zh-Hant" => GetPaddleOcrFactory(OnlineFullModels.TraditionalChineseV3),
            "ja" => GetPaddleOcrFactory(OnlineFullModels.JapanV3),
            "en" => GetPaddleOcrFactory(OnlineFullModels.EnglishV3),
            _ => throw new ArgumentOutOfRangeException(nameof(_script), _script, "Unsupported script.")
        })(stoppingToken);

    public IEnumerable<PaddleOcrRecognitionResult> RecognizeImageMatrices(Dictionary<uint, Mat> matricesKeyByImageId)
    {
        if (_ocr == null) throw new("PaddleOcr model haven't been initialized.");
        return matricesKeyByImageId.SelectMany(pair =>
            CreateRecognitionResult(pair.Key, _script, _ocr.Run(pair.Value)));
    }

    private static IEnumerable<PaddleOcrRecognitionResult> CreateRecognitionResult
        (uint imageId, string script, PaddleOcrResult result) =>
        result.Regions.Select(region => new PaddleOcrRecognitionResult(
            imageId, script, region.Rect, region.Text, (region.Score * 100).NanToZero().RoundToUshort()));

    public record DetectionResult(uint ImageId, RotatedRect TextBox);

    public IEnumerable<DetectionResult> DetectImageMatrices(Dictionary<uint, Mat> matricesKeyByImageId)
    {
        if (_ocr == null) throw new("PaddleOcr haven't been initialized.");
        return matricesKeyByImageId.SelectMany(pair =>
            _ocr.Detector.Run(pair.Value).Select(rect => new DetectionResult(pair.Key, rect)));
    }
}
