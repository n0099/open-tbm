using OpenCvSharp;
using Sdcb.PaddleInference;
using Sdcb.PaddleOCR;
using Sdcb.PaddleOCR.Models;
using Sdcb.PaddleOCR.Models.Online;

namespace tbm.Crawler.ImagePipeline.Ocr;

public class PaddleOcrRecognizerAndDetector
{
    private Dictionary<string, PaddleOcrAll> _modelsKeyByScript = new();

    public PaddleOcrRecognizerAndDetector(IConfiguration config) => Settings.GlobalModelDirectory =
        config.GetSection("ImageOcrPipeline").GetSection("PaddleOcr")
            .GetValue("ModelPath", "./PaddleOcrModels") ?? "./PaddleOcrModels";

    public void Dispose() => _modelsKeyByScript.ForEach(pair => pair.Value.Dispose());

    public async Task InitializeModels(CancellationToken stoppingToken)
    {
        PaddleOcrAll Create(FullOcrModel model) =>
            new(model, PaddleDevice.Mkldnn())
            {
                AllowRotateDetection = true,
                Enable180Classification = true
            };
        _modelsKeyByScript = new()
        {
            {"zh-Hans", Create(await OnlineFullModels.ChineseV3.DownloadAsync(stoppingToken))},
            {"zh-Hant", Create(await OnlineFullModels.TranditionalChinseV3.DownloadAsync(stoppingToken))},
            { // https://github.com/sdcb/PaddleSharp/pull/35
                "ja", Create(await new OnlineFullModels(
                    OnlineDetectionModel.MultiLanguageV3,
                    OnlineClassificationModel.ChineseMobileV2,
                    LocalDictOnlineRecognizationModel.JapanV3
                ).DownloadAsync(stoppingToken))
            },
            {"en", Create(await OnlineFullModels.EnglishV3.DownloadAsync(stoppingToken))}
        };
    }

    public IEnumerable<PaddleOcrRecognitionResult> RecognizeImageMatrices(Dictionary<string, Mat> matricesKeyByImageId) =>
        matricesKeyByImageId.SelectMany(matrix => _modelsKeyByScript.SelectMany(model =>
            CreateRecognitionResult(matrix.Key, model.Key, model.Value.Run(matrix.Value))));

    private static IEnumerable<PaddleOcrRecognitionResult> CreateRecognitionResult
        (string imageId, string script, PaddleOcrResult result) =>
        result.Regions.Select(region => new PaddleOcrRecognitionResult(
            imageId, script, region.Rect, region.Text, (region.Score * 100).NanToZero().RoundToUshort()));

    public record DetectionResult(string ImageId, RotatedRect TextBox);

    public IEnumerable<DetectionResult> DetectImageMatrices(Dictionary<string, Mat> matricesKeyByImageId) =>
        matricesKeyByImageId.SelectMany(matrix => _modelsKeyByScript.SelectMany(model =>
            model.Value.Detector.Run(matrix.Value).Select(rect => new DetectionResult(matrix.Key, rect))));
}
