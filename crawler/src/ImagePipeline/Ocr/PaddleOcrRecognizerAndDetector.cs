using OpenCvSharp;
using Sdcb.PaddleInference;
using Sdcb.PaddleOCR;
using Sdcb.PaddleOCR.Models.Online;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class PaddleOcrRecognizerAndDetector : IDisposable
{
    private readonly string _script;
    private PaddleOcrAll? _model;

    public delegate PaddleOcrRecognizerAndDetector New(string script);

    public PaddleOcrRecognizerAndDetector(IConfiguration config, string script)
    {
        _script = script;
        Settings.GlobalModelDirectory =
            config.GetSection("ImageOcrPipeline").GetSection("PaddleOcr")
                .GetValue("ModelPath", "./PaddleOcrModels")
            ?? "./PaddleOcrModels";
    }

    public void Dispose() => _model?.Dispose();

    private static Func<CancellationToken, Task<PaddleOcrAll>>
        GetModelFactory(OnlineFullModels model) => async stoppingToken =>
        new(await model.DownloadAsync(stoppingToken), PaddleDevice.Mkldnn())
        {
            AllowRotateDetection = true,
            Enable180Classification = true
        };

    private static readonly Dictionary<string, Func<CancellationToken, Task<PaddleOcrAll>>> AvailableModelKeyByScript = new()
    {
        {"zh-Hans", GetModelFactory(OnlineFullModels.ChineseV3)},
        {"zh-Hant", GetModelFactory(OnlineFullModels.TraditionalChineseV3)},
        {"ja", GetModelFactory(OnlineFullModels.JapanV3)},
        {"en", GetModelFactory(OnlineFullModels.EnglishV3)}
    };

    public async Task InitializeModel(CancellationToken stoppingToken = default) =>
        _model ??= await AvailableModelKeyByScript[_script](stoppingToken);

    public IEnumerable<PaddleOcrRecognitionResult> RecognizeImageMatrices(Dictionary<string, Mat> matricesKeyByImageId)
    {
        if (_model == null) throw new("PaddleOcr model haven't been initialized.");
        return matricesKeyByImageId.SelectMany(matrix =>
            CreateRecognitionResult(matrix.Key, _script, _model.Run(matrix.Value)));
    }

    private static IEnumerable<PaddleOcrRecognitionResult> CreateRecognitionResult
        (string imageId, string script, PaddleOcrResult result) =>
        result.Regions.Select(region => new PaddleOcrRecognitionResult(
            imageId, script, region.Rect, region.Text, (region.Score * 100).NanToZero().RoundToUshort()));

    public record DetectionResult(string ImageId, RotatedRect TextBox);

    public IEnumerable<DetectionResult> DetectImageMatrices(Dictionary<string, Mat> matricesKeyByImageId)
    {
        if (_model == null) throw new("PaddleOcr model haven't been initialized.");
        return matricesKeyByImageId.SelectMany(matrix =>
            _model.Detector.Run(matrix.Value).Select(rect => new DetectionResult(matrix.Key, rect)));
    }
}
