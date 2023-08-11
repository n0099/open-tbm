using OpenCvSharp.Text;

namespace tbm.ImagePipeline.Ocr;

public class TesseractRecognizer(IConfiguration config, string script) : IDisposable
{
    public delegate TesseractRecognizer New(string script);

    private readonly IConfigurationSection _config = config.GetSection("OcrConsumer:Tesseract");
    private Lazy<OCRTesseract> TesseractInstanceHorizontal => new(script switch
    {
        "zh-Hans" => CreateTesseract("best/chi_sim+best/eng"),
        "zh-Hant" => CreateTesseract("best/chi_tra+best/eng"),
        "ja" => CreateTesseract("best/jpn"), // literal latin letters in japanese is replaced by katakana
        "en" => CreateTesseract("best/eng"),
        _ => throw new ArgumentOutOfRangeException(nameof(script), script, "Unsupported script.")
    });
    private Lazy<OCRTesseract> TesseractInstanceVertical => new(script switch
    {
        "zh-Hans" => CreateTesseract("best/chi_sim_vert", isVertical: true),
        "zh-Hant" => CreateTesseract("best/chi_tra_vert", isVertical: true),
        "ja" => CreateTesseract("best/jpn_vert", isVertical: true),
        "en" => TesseractInstanceHorizontal.Value, // fallback to best/eng since there's no best/eng_vert
        _ => throw new ArgumentOutOfRangeException(nameof(script), script, "Unsupported script.")
    });
    private int ConfidenceThreshold => _config.GetValue("ConfidenceThreshold", 20);
    private float AspectRatioThresholdToConsiderAsVertical => _config.GetValue("AspectRatioThresholdToConsiderAsVertical", 0.8f);

    private OCRTesseract CreateTesseract(string scripts, bool isVertical = false) =>
        // https://github.com/shimat/opencvsharp/issues/873#issuecomment-1458868153
        // https://pyimagesearch.com/2021/11/15/tesseract-page-segmentation-modes-psms-explained-how-to-improve-your-ocr-accuracy/
        OCRTesseract.Create(_config.GetValue("DataPath", "") ?? "",
            scripts, charWhitelist: "", oem: 1, psmode: isVertical ? 5 : 7);

    public void Dispose()
    {
        TesseractInstanceHorizontal.Value.Dispose();
        TesseractInstanceVertical.Value.Dispose();
    }

    public record PreprocessedTextBox(ImageKey ImageKey, RotatedRect TextBox, Mat PreprocessedTextBoxMat);

    public static IEnumerable<PreprocessedTextBox> PreprocessTextBoxes(
        ImageKey imageKey,
        Mat originalMatrix,
        IEnumerable<RotatedRect> textBoxes,
        CancellationToken stoppingToken = default
    ) => textBoxes.Select(textBox =>
    {
        stoppingToken.ThrowIfCancellationRequested();
        // not using RotatedRect.Angle directly since it's not based on a stable order of four vertices
        var degrees = GetRotationDegrees(textBox); // https://github.com/opencv/opencv/issues/23335
        // crop by circumscribed rectangle, intersect will prevent textBox outside originalMatrix
        var mat = new Mat(originalMatrix, new Rect(new(), originalMatrix.Size()).Intersect(textBox.BoundingRect()));

        Cv2.CvtColor(mat, mat, ColorConversionCodes.BGR2GRAY);
        // https://docs.opencv.org/4.7.0/d7/d4d/tutorial_py_thresholding.html
        // http://www.fmwconcepts.com/imagemagick/threshold_comparison/index.php
        _ = Cv2.Threshold(mat, mat, thresh: 0, maxval: 255, ThresholdTypes.Otsu | ThresholdTypes.Binary);

        if (degrees != 0) RotateMatrix(mat, degrees);

        // https://github.com/tesseract-ocr/tesseract/issues/427
        Cv2.CopyMakeBorder(mat, mat, 10, 10, 10, 10, BorderTypes.Constant, new(0, 0, 0));

        // https://github.com/tesseract-ocr/tesseract/issues/3001
        return mat.Width < 3 ? null : new PreprocessedTextBox(imageKey, textBox, mat);
    }).OfType<PreprocessedTextBox>();

    private static float GetRotationDegrees(RotatedRect rotatedRect)
    { // https://stackoverflow.com/questions/13002979/how-to-calculate-rotation-angle-from-rectangle-points
        var (topLeft, topRight, bottomLeft, bottomRight) = rotatedRect.GetPoints();
        if (topLeft.X == bottomLeft.X
            && topRight.X == bottomRight.X
            && topLeft.Y == topRight.Y
            && bottomLeft.Y == bottomRight.Y) return 0;
        var xAxisDiff = bottomLeft.X - topLeft.X;
        var yAxisDiff = bottomLeft.Y - topLeft.Y;
        // https://www.calculator.net/triangle-calculator.html?vc=&vx=4&vy=&va=90&vz=1&vb=&angleunits=d&x=53&y=29
        return (float)(Math.Atan2(xAxisDiff, yAxisDiff) * 180 / Math.PI); // radians to degrees
    }

    private static void RotateMatrix(Mat src, float degrees)
    { // https://stackoverflow.com/questions/22041699/rotate-an-image-without-cropping-in-opencv-in-c/75451191#75451191
        degrees = -degrees; // counter-clockwise to clockwise
        var center = new Point2f((src.Width - 1) / 2f, (src.Height - 1) / 2f);
        using var rotationMat = Cv2.GetRotationMatrix2D(center, degrees, scale: 1);
        var boundingRect = new RotatedRect(new(), new(src.Width, src.Height), degrees).BoundingRect();
        rotationMat.Set(0, 2, rotationMat.Get<double>(0, 2) + (boundingRect.Width / 2f) - (src.Width / 2f));
        rotationMat.Set(1, 2, rotationMat.Get<double>(1, 2) + (boundingRect.Height / 2f) - (src.Height / 2f));
        Cv2.WarpAffine(src, src, rotationMat, boundingRect.Size);
    }

    public TesseractRecognitionResult RecognizePreprocessedTextBox
        (PreprocessedTextBox textBox, CancellationToken stoppingToken = default)
    {
        stoppingToken.ThrowIfCancellationRequested();
        var (imageKey, box, preprocessedTextBoxMat) = textBox;
        using var mat = preprocessedTextBoxMat;
        var isVertical = (float)mat.Width / mat.Height < AspectRatioThresholdToConsiderAsVertical;
        if (isVertical && script == "en") isVertical = false; // there's no vertical english
        var tesseract = isVertical ? TesseractInstanceVertical : TesseractInstanceHorizontal;
        tesseract.Value.Run(mat, out _, out var rects, out var texts, out var confidences);

        var shouldFallbackToPaddleOcr = !rects.Any();
        var components = rects.EquiZip(texts, confidences)
            .Select(t => (Rect: t.Item1, Text: t.Item2, Confidence: t.Item3))
            .Where(t => t.Confidence > ConfidenceThreshold)
            .ToList();
        var text = string.Join("", components.Select(t => t.Text)).Trim();
        if (text == "") shouldFallbackToPaddleOcr = true;
        var averageConfidence = components.Any()
            ? components.Select(c => c.Confidence).Average().RoundToUshort()
            : (ushort)0;

        return new(imageKey, box, text, averageConfidence, isVertical, shouldFallbackToPaddleOcr);
    }
}
