using System.Security.Cryptography;
using System.Text;

namespace tbm.Crawler.Tieba;

public class ClientRequester
{
    private readonly ILogger<ClientRequester> _logger;
    private readonly IConfigurationSection _config;
    private readonly IHttpClientFactory _httpFactory;
    private readonly ClientRequesterTcs _requesterTcs;
    private static readonly Random Rand = new();

    public ClientRequester(ILogger<ClientRequester> logger, IConfiguration config,
        IHttpClientFactory httpFactory, ClientRequesterTcs requesterTcs)
    {
        (_logger, _httpFactory, _requesterTcs) = (logger, httpFactory, requesterTcs);
        _config = config.GetSection("ClientRequester");
    }

    public Task<JsonElement> RequestJson
        (string url, string clientVersion, Dictionary<string, string> postParam, CancellationToken stoppingToken = default) =>
        Request(() => PostJson(url, postParam, clientVersion, stoppingToken), stream =>
        {
            stoppingToken.ThrowIfCancellationRequested();
            using var doc = JsonDocument.Parse(stream);
            return doc.RootElement.Clone();
        });

    public Task<TResponse> RequestProtoBuf<TRequest, TResponse>(
        string url, string clientVersion,
        TRequest requestParam, Action<TRequest, Common> commonParamSetter,
        Func<TResponse> responseFactory, CancellationToken stoppingToken = default
    )
        where TRequest : IMessage<TRequest>
        where TResponse : IMessage<TResponse> =>
        Request(() => PostProtoBuf(url, clientVersion, requestParam, commonParamSetter, stoppingToken), stream =>
        {
            try
            {
                stoppingToken.ThrowIfCancellationRequested();
                return new MessageParser<TResponse>(responseFactory).ParseFrom(stream);
            }
            catch (InvalidProtocolBufferException e)
            {
                _ = stream.Seek(0, SeekOrigin.Begin);
                var stream2 = new MemoryStream((int)stream.Length);
                stream.CopyTo(stream2);
                // the invalid protoBuf bytes usually is just a plain html string
                var responseBody = Encoding.UTF8.GetString(stream2.ToArray());
                if (responseBody.Contains("为了保护您的账号安全和最佳的浏览体验，当前业务已经不支持IE8以下浏览器"))
                    throw new TiebaException(true, true);
                throw new TiebaException($"Malformed protoBuf response from tieba. {responseBody}", e);
            }
        });

    private static async Task<T> Request<T>(Func<Task<HttpResponseMessage>> requester, Func<Stream, T> responseConsumer)
    {
        try
        {
            using var response = await requester();
            var stream = await response.EnsureSuccessStatusCode().Content.ReadAsStreamAsync();
            return responseConsumer(stream);
        }
        catch (TaskCanceledException e) when (e.InnerException is TimeoutException)
        {
            throw new TiebaException("Tieba client request timeout.");
        }
        catch (HttpRequestException e)
        {
            if (e.StatusCode == null)
                throw new TiebaException("Network error from tieba.", e);
            throw new TiebaException($"HTTP {(int)e.StatusCode} from tieba.");
        }
    }

    private Task<HttpResponseMessage> PostJson
        (string url, Dictionary<string, string> postParam, string clientVersion, CancellationToken stoppingToken = default)
    {
        var postData = new Dictionary<string, string>
        {
            {"_client_id", $"wappc_{Rand.NextLong(1000000000000, 9999999999999)}_{Rand.Next(100, 999)}"},
            {"_client_type", "2"},
            {"_client_version", clientVersion}
        }.Concat(postParam).ToList();
        var sign = postData.Aggregate("", (acc, pair) =>
        {
            acc += pair.Key + '=' + pair.Value;
            return acc;
        }) + "tiebaclient!!!";
        var signMd5 = BitConverter.ToString(MD5.HashData(Encoding.UTF8.GetBytes(sign))).Replace("-", "");
        postData.Add(KeyValuePair.Create("sign", signMd5));

        return Post(http => http.PostAsync(url, new FormUrlEncodedContent(postData), stoppingToken),
            () => _logger.LogTrace("POST {} {}", url, postParam));
    }

    private Task<HttpResponseMessage> PostProtoBuf<TRequest>(
        string url, string clientVersion,
        TRequest requestParam, Action<TRequest, Common> commonParamSetter,
        CancellationToken stoppingToken = default
    )
        where TRequest : IMessage<TRequest>
    {
        // https://github.com/Starry-OvO/aiotieba/issues/67#issuecomment-1376006123
        // https://github.com/MoeNetwork/wmzz_post/blob/80aba25de46f5b2cb1a15aa2a69b527a7374ffa9/wmzz_post_setting.php#L64
        commonParamSetter(requestParam, new() {ClientVersion = clientVersion, ClientType = 2});

        // https://github.com/dotnet/runtime/issues/22996 http://test.greenbytes.de/tech/tc2231
        var protoBufFile = new ByteArrayContent(requestParam.ToByteArray());
        protoBufFile.Headers.Add("Content-Disposition", "form-data; name=\"data\"; filename=\"file\"");
        var content = new MultipartFormDataContent {protoBufFile};
        // https://stackoverflow.com/questions/30926645/httpcontent-boundary-double-quotes
        var boundary = content.Headers.ContentType?.Parameters.First(header => header.Name == "boundary");
        if (boundary != null) boundary.Value = boundary.Value?.Replace("\"", "");

        var request = new HttpRequestMessage(HttpMethod.Post, url) {Content = content};
        _ = request.Headers.UserAgent.TryParseAdd($"bdtb for Android {clientVersion}");
        request.Headers.Add("x_bd_data_type", "protobuf");
        request.Headers.Accept.ParseAdd("*/*");
        request.Headers.Connection.Add("keep-alive");

        return Post(http => http.SendAsync(request, stoppingToken),
            () => _logger.LogTrace("POST {} {}", url, requestParam));
    }

    private Task<HttpResponseMessage> Post
        (Func<HttpClient, Task<HttpResponseMessage>> responseTaskFactory, Action logTraceCallback)
    {
        var http = _httpFactory.CreateClient("tbClient");
        _requesterTcs.Wait();
        if (_config.GetValue("LogTrace", false)) logTraceCallback();
        var ret = responseTaskFactory(http);
        _ = ret.ContinueWith(task =>
        {
            if (task.IsCompletedSuccessfully && task.Result.IsSuccessStatusCode) _requesterTcs.Increase();
            else _requesterTcs.Decrease();
        }, TaskContinuationOptions.ExecuteSynchronously);
        return ret;
    }
}
