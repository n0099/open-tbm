namespace tbm.Crawler
{
    public class ClientRequester
    {
        public class HttpClient : System.Net.Http.HttpClient
        {
        }

        private readonly ILogger<ClientRequester> _logger;
        private readonly IConfigurationSection _config;
        private readonly ClientRequesterTcs _clientRequesterTcs;
        private static HttpClient _http = new();
        private static readonly Random Rand = new();

        public ClientRequester(ILogger<ClientRequester> logger, IConfiguration config,
            HttpClient http, ClientRequesterTcs clientRequesterTcs)
        {
            _logger = logger;
            _config = config.GetSection("ClientRequester");
            _clientRequesterTcs = clientRequesterTcs;
            _http = http;
        }

        public Task<JsonElement> RequestJson(string url, Dictionary<string, string> param, string clientVersion) =>
            Request(() => PostJson(url, param, clientVersion), stream =>
            {
                using var doc = JsonDocument.Parse(stream);
                return doc.RootElement.Clone();
            });

        public Task<TResponse> RequestProtoBuf<TRequest, TResponse>(string url, TRequest param, string clientVersion)
            where TRequest : IMessage where TResponse : IMessage<TResponse>, new() =>
            Request(() => PostProtoBuf(url, param, clientVersion),
                stream => new MessageParser<TResponse>(() => new TResponse()).ParseFrom(stream));

        private static async Task<T> Request<T>(Func<Task<HttpResponseMessage>> requester, Func<Stream, T> responseConsumer)
        {
            try
            {
                await using var stream = (await requester()).EnsureSuccessStatusCode().Content.ReadAsStream();
                await using var gzip = new GZipStream(stream, CompressionMode.Decompress);
                return responseConsumer(gzip);
            }
            catch (TaskCanceledException e) when (e.InnerException is TimeoutException)
            {
                throw new TiebaException($"Tieba client request timeout, {e.Message}");
            }
        }

        private Task<HttpResponseMessage> PostJson(string url, Dictionary<string, string> data, string clientVersion)
        {
            Dictionary<string, string> clientInfo = new()
            {
                {"_client_id", $"wappc_{Rand.NextLong(1000000000000, 9999999999999)}_{Rand.Next(100, 999)}"},
                {"_client_type", "2"},
                {"_client_version", clientVersion}
            };
            var postData = clientInfo.Concat(data).ToList();
            var sign = postData.Aggregate("", (acc, i) =>
            {
                acc += i.Key + '=' + i.Value;
                return acc;
            }) + "tiebaclient!!!";
            var signMd5 = BitConverter.ToString(MD5.HashData(Encoding.UTF8.GetBytes(sign))).Replace("-", "");
            postData.Add(KeyValuePair.Create("sign", signMd5));

            return Post(() => _http.PostAsync(url, new FormUrlEncodedContent(postData)),
                () => _logger.LogTrace("POST {} {}", url, data));
        }

        private Task<HttpResponseMessage> PostProtoBuf(string url, IMessage paramsProtoBuf, string clientVersion)
        {
            var dataField = paramsProtoBuf.Descriptor.FindFieldByName("data");
            var dataFieldValue = (IMessage)dataField.Accessor.GetValue(paramsProtoBuf);
            dataField.MessageType.FindFieldByName("common").Accessor.SetValue(dataFieldValue, new Common {ClientVersion = clientVersion});

            // https://github.com/dotnet/runtime/issues/22996, http://test.greenbytes.de/tech/tc2231
            var protoBufFile = new ByteArrayContent(paramsProtoBuf.ToByteArray());
            protoBufFile.Headers.Add("Content-Disposition", "form-data; name=\"data\"; filename=\"file\"");
            var content = new MultipartFormDataContent {protoBufFile};
            // https://stackoverflow.com/questions/30926645/httpcontent-boundary-double-quotes
            var boundary = content.Headers.ContentType?.Parameters.First(o => o.Name == "boundary");
            if (boundary != null) boundary.Value = boundary.Value?.Replace("\"", "");

            var request = new HttpRequestMessage(HttpMethod.Post, url) {Content = content};
            _ = request.Headers.UserAgent.TryParseAdd($"bdtb for Android {clientVersion}");
            request.Headers.Add("x_bd_data_type", "protobuf");
            request.Headers.AcceptEncoding.ParseAdd("gzip");
            request.Headers.Accept.ParseAdd("*/*");
            request.Headers.Connection.Add("keep-alive");

            return Post(() => _http.SendAsync(request),
                () => _logger.LogTrace("POST {} {}", url, paramsProtoBuf));
        }

        private Task<HttpResponseMessage> Post(Func<Task<HttpResponseMessage>> postCallback, Action logTraceCallback)
        {
            _clientRequesterTcs.Wait();
            var res = postCallback();
            if (_config.GetValue("LogTrace", false)) logTraceCallback();
            _ = res.ContinueWith(i =>
            {
                if (i.IsCompletedSuccessfully && i.Result.IsSuccessStatusCode) _clientRequesterTcs.Increase();
                else _clientRequesterTcs.Decrease();
            });
            return res;
        }
    }
}
