using System.Net;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Http;

namespace tbm.ImagePipeline;

public partial class EntryPoint : BaseEntryPoint
{
    [SuppressMessage("Style", "IDE0058:Expression value is never used")]
    protected override void ConfigureServices(HostBuilderContext context, IServiceCollection service)
    {
        service.AddHostedService<ImageBatchConsumingWorker>();
        service.AddHostedService<ImageBatchProducingWorker>();

        var imageRequesterConfig = context.Configuration.GetSection("ImageRequester");
        service.AddHttpClient("tbImage", client =>
            {
                client.BaseAddress = new("https://imgsrc.baidu.com/forum/pic/item/");
                client.Timeout = Timeout.InfiniteTimeSpan; // overall timeout, will sum up after each retry by polly
                client.DefaultRequestVersion = HttpVersion.Version20;
                client.DefaultRequestHeaders.UserAgent.TryParseAdd(
                    imageRequesterConfig.GetValue("UserAgent", Strings.DefaultUserAgent));

                // https://github.com/w3c/webappsec/issues/382
                client.DefaultRequestHeaders.Referrer = new("https://tieba.baidu.com/");
            })
            .SetHandlerLifetime(TimeSpan.FromSeconds(
                imageRequesterConfig.GetValue("HandlerLifetimeSec", 600))); // 10 mins

        var registry = service.AddPolicyRegistry();
        AddPolicyToRegistry(registry, imageRequesterConfig,
            HttpPolicyExtensions.HandleTransientHttpError(),
            (logger, imageUrlFilename, outcome, tryCount) =>
            {
                var failReason = outcome.Exception == null ? $"HTTP {outcome.Result.StatusCode}" : "exception";
                logger.LogWarning(outcome.Exception,
                    "Fetch for image {} failed due to {} after {} retries, still trying...",
                    imageUrlFilename, failReason, tryCount);
            });

        // https://stackoverflow.com/questions/69749167/polly-handletransienthttperror-not-catching-httprequestexception/69750053#69750053
        AddPolicyToRegistry(registry, imageRequesterConfig,
            Policy<byte[]>.Handle<HttpRequestException>(),
            (logger, imageUrlFilename, outcome, tryCount) => logger.LogWarning(outcome.Exception,
                "Fetch for image {} failed after {} bytes downloaded due to exception after {} retries, still trying...",
                imageUrlFilename, outcome.Result.Length, tryCount));

        // https://stackoverflow.com/questions/52889827/remove-http-client-logging-handler-in-asp-net-core/52970073#52970073
        service.RemoveAll<IHttpMessageHandlerBuilderFilter>();
    }

    [SuppressMessage("Style", "IDE0058:Expression value is never used")]
    protected override void ConfigureContainer(HostBuilderContext context, ContainerBuilder builder)
    {
        builder.RegisterImplementsOfBaseTypes(typeof(EntryPoint).Assembly, [typeof(IConsumer<>)]);
        builder.RegisterType<ImagePipelineDbContext>();
        builder.RegisterType<JointRecognizer>();
        builder.RegisterType<ImageRequester>();

        builder.Register(_ => Channel.CreateBounded<List<ImageWithBytes>>(
                new BoundedChannelOptions(context.Configuration
                    .GetSection("ImageBatchProducer")
                    .GetValue("MaxBufferedImageBatches", 4))
                    {SingleReader = true, SingleWriter = true}))
            .SingleInstance();
        builder.Register(_ =>
        {
            var limitRps = context.Configuration
                .GetSection("ImageRequester").GetValue("LimitRps", 10);
            return new FixedWindowRateLimiter(new()
                {PermitLimit = limitRps, QueueLimit = limitRps, Window = TimeSpan.FromSeconds(1)});
        }).SingleInstance();

        var config = context.Configuration.GetSection("OcrConsumer");
        var paddleOcr = builder.RegisterType<PaddleOcrProvider>();
        if (!config.GetSection("PaddleOcr").GetValue("DisposeAfterEachBatch", false))
            paddleOcr.SingleInstance();
        var tesseract = builder.RegisterType<TesseractRecognizer>();
        if (!config.GetSection("Tesseract").GetValue("DisposeAfterEachBatch", false))
            tesseract.SingleInstance();
    }
}
public partial class EntryPoint
{
    private delegate void PolicyOnRetryLogDelegate<T>
        (ILogger<ImageRequester> logger, string imageUrlFilename, DelegateResult<T> outcome, int tryCount);

    private static void AddPolicyToRegistry<T>(
        IPolicyRegistry<string> registry,
        IConfiguration imageRequesterConfig,
        PolicyBuilder<T> policyBuilder,
        PolicyOnRetryLogDelegate<T> onRetryLogDelegate) =>
        registry.Add($"tbImage<{typeof(T).Name}>", Policy.WrapAsync(
            policyBuilder.RetryAsync(
                imageRequesterConfig.GetValue("MaxRetryTimes", 5),
                (outcome, tryCount, policyContext) =>
                { // https://www.stevejgordon.co.uk/passing-an-ilogger-to-polly-policies
                    if (policyContext.TryGetValue("ILogger<ImageRequester>", out var o1)
                        && o1 is ILogger<ImageRequester> logger
                        && policyContext.TryGetValue("imageUrlFilename", out var o2)
                        && o2 is string imageUrlFilename)
                    {
                        onRetryLogDelegate(logger, imageUrlFilename, outcome, tryCount);
                    }
                }),
            Policy.TimeoutAsync<T>( // timeout for each retry: https://github.com/App-vNext/Polly/wiki/Polly-and-HttpClientFactory/abbe6d767681098c957ee6b6bee656197b7d03b4#use-case-applying-timeouts
                imageRequesterConfig.GetValue("TimeoutMs", 3000))));
}
