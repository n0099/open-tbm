using Sdcb.PaddleInference;
using Sdcb.PaddleOCR;
using Sdcb.PaddleOCR.Models.Online;

namespace tbm.ImagePipeline.Ocr;

public sealed partial class PaddleOcrRecognizerAndDetector : IDisposable
{
    private readonly IConfigurationSection _config;
    private readonly string _script;
    private PaddleOcrAll? _ocr;

    public PaddleOcrRecognizerAndDetector(IConfiguration config, string script)
    {
        _config = config.GetSection("OcrConsumer:PaddleOcr");
        _script = script;
        Settings.GlobalModelDirectory =
            _config.GetValue("ModelPath", "./PaddleOcrModels") ?? "./PaddleOcrModels";
    }

    public delegate PaddleOcrRecognizerAndDetector New(string script);

    public void Dispose() => _ocr?.Dispose();

    [SuppressMessage("Major Code Smell", "S3928:Parameter names used into ArgumentException constructors should match an existing one ", Justification = "https://github.com/SonarSource/sonar-dotnet/issues/8386#issuecomment-1847872210")]
    [SuppressMessage("StyleCop.CSharp.MaintainabilityRules", "SA1119:Statement should not use unnecessary parenthesis", Justification = "https://github.com/DotNetAnalyzers/StyleCopAnalyzers/issues/3730")]
    [SuppressMessage("Usage", "CA2208:Instantiate argument exceptions correctly")]
    public async Task Initialize(CancellationToken stoppingToken = default) =>
        _ocr ??= await (_script switch
        { // https://en.wikipedia.org/wiki/Template:ISO_15924_script_codes_and_related_Unicode_data
            "Hans" => GetPaddleOcrFactory(OnlineFullModels.ChineseV4),
            "Hant" => GetPaddleOcrFactory(OnlineFullModels.TraditionalChineseV3),
            "Jpan" => GetPaddleOcrFactory(OnlineFullModels.JapanV4),
            "Latn" => GetPaddleOcrFactory(OnlineFullModels.EnglishV4),
            _ => throw new ArgumentOutOfRangeException(nameof(_script), _script, "Unsupported script.")
        })(stoppingToken);

    private Func<CancellationToken, Task<PaddleOcrAll>>
        GetPaddleOcrFactory(OnlineFullModels model) => async stoppingToken =>
        new(await model.DownloadAsync(stoppingToken), PaddleDevice.Mkldnn(
            _config.GetValue("MkldnnCacheCapacity", 1), // https://github.com/sdcb/PaddleSharp/pull/46
            _config.GetValue("CpuMathThreadCount", 0),
            _config.GetValue("MemoryOptimized", true),
            _config.GetValue("StdoutLogEnabled", false)))
        {
            AllowRotateDetection = true,
            Enable180Classification = true
        };
}
public sealed partial class PaddleOcrRecognizerAndDetector
{
    public IEnumerable<Either<ImageId, IEnumerable<PaddleOcrRecognitionResult>>> RecognizeMatrices(
        Dictionary<ImageKey, Mat> matricesKeyByImageKey,
        FailedImageHandler failedImageHandler,
        CancellationToken stoppingToken = default)
    {
        Guard.IsNotNull(_ocr);
        return failedImageHandler.TrySelect(matricesKeyByImageKey,
            pair => pair.Key.ImageId,
            pair =>
            {
                stoppingToken.ThrowIfCancellationRequested();
                return _ocr.Run(pair.Value).Regions.Select(region => new PaddleOcrRecognitionResult(
                    pair.Key, region.Rect, region.Text,
                    (region.Score * 100).NanToZero().RoundToByte(),
                    _ocr.Recognizer.Model.Version));
            });
    }

    public IEnumerable<Either<ImageId, IEnumerable<DetectionResult>>> DetectMatrices(
        Dictionary<ImageKey, Mat> matricesKeyByImageKey,
        FailedImageHandler failedImageHandler,
        CancellationToken stoppingToken = default)
    {
        Guard.IsNotNull(_ocr);
        return failedImageHandler.TrySelect(matricesKeyByImageKey,
            pair => pair.Key.ImageId,
            pair =>
            {
                stoppingToken.ThrowIfCancellationRequested();
                return _ocr.Detector.Run(pair.Value).Select(rect => new DetectionResult(pair.Key, rect));
            });
    }

    public record DetectionResult(ImageKey ImageKey, RotatedRect TextBox);
}
