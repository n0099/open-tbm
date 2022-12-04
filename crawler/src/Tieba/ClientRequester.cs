namespace tbm.Crawler.Tieba
{
    public class ClientRequester
    {
        private readonly ILogger<ClientRequester> _logger;
        private readonly IConfigurationSection _config;
        private readonly ClientRequesterTcs _requesterTcs;
        private static HttpClient _http = null!;
        private static readonly Random Rand = new();

        public ClientRequester(ILogger<ClientRequester> logger, IConfiguration config,
            IHttpClientFactory httpFactory, ClientRequesterTcs requesterTcs)
        {
            _logger = logger;
            _config = config.GetSection("ClientRequester");
            _http = httpFactory.CreateClient("tbClient");
            _requesterTcs = requesterTcs;
        }

        public Task<JsonElement> RequestJson(string url, string clientVersion, Dictionary<string, string> param) =>
            Request(() => PostJson(url, param, clientVersion), stream =>
            {
                using var doc = JsonDocument.Parse(stream);
                return doc.RootElement.Clone();
            });

        public Task<TResponse> RequestProtoBuf<TRequest, TResponse>
            (string url, string clientVersion, PropertyInfo paramDataProp, PropertyInfo paramCommonProp, Func<TResponse> responseFactory, TRequest param)
            where TRequest : IMessage<TRequest> where TResponse : IMessage<TResponse> =>
            Request(() => PostProtoBuf(url, clientVersion, param, paramDataProp, paramCommonProp), stream =>
            {
                try
                {
                    return new MessageParser<TResponse>(responseFactory).ParseFrom(stream);
                }
                catch (InvalidProtocolBufferException e)
                { // the invalid protoBuf bytes usually is just a plain html string
                    _ = stream.Seek(0, SeekOrigin.Begin);
                    var stream2 = new MemoryStream((int)stream.Length);
                    stream.CopyTo(stream2);
                    throw new TiebaException($"Malformed protoBuf response from tieba. {Encoding.UTF8.GetString(stream2.ToArray())}", e);
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
                if (e.StatusCode == null) throw new TiebaException("Network error from tieba.", e);
                throw new TiebaException($"HTTP {(int)e.StatusCode} from tieba.");
            }
        }

        private Task<HttpResponseMessage> PostJson(string url, Dictionary<string, string> data, string clientVersion)
        {
            var clientInfo = new Dictionary<string, string>
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

        private Task<HttpResponseMessage> PostProtoBuf(string url, string clientVersion, IMessage paramProtoBuf, PropertyInfo dataProp, PropertyInfo commonProp)
        {
            commonProp.SetValue(dataProp.GetValue(paramProtoBuf), new Common {ClientVersion = clientVersion});

            // https://github.com/dotnet/runtime/issues/22996, http://test.greenbytes.de/tech/tc2231
            var protoBufFile = new ByteArrayContent(paramProtoBuf.ToByteArray());
            protoBufFile.Headers.Add("Content-Disposition", "form-data; name=\"data\"; filename=\"file\"");
            var content = new MultipartFormDataContent {protoBufFile};
            // https://stackoverflow.com/questions/30926645/httpcontent-boundary-double-quotes
            var boundary = content.Headers.ContentType?.Parameters.First(i => i.Name == "boundary");
            if (boundary != null) boundary.Value = boundary.Value?.Replace("\"", "");

            var request = new HttpRequestMessage(HttpMethod.Post, url) {Content = content};
            _ = request.Headers.UserAgent.TryParseAdd($"bdtb for Android {clientVersion}");
            request.Headers.Add("x_bd_data_type", "protobuf");
            request.Headers.Accept.ParseAdd("*/*");
            request.Headers.Connection.Add("keep-alive");

            return Post(() => _http.SendAsync(request),
                () => _logger.LogTrace("POST {} {}", url, paramProtoBuf));
        }

        private Task<HttpResponseMessage> Post(Func<Task<HttpResponseMessage>> responseTaskFactory, Action logTraceCallback)
        {
            _requesterTcs.Wait();
            if (_config.GetValue("LogTrace", false)) logTraceCallback();
            var ret = responseTaskFactory();
            _ = ret.ContinueWith(i =>
            {
                if (i.IsCompletedSuccessfully && i.Result.IsSuccessStatusCode) _requesterTcs.Increase();
                else _requesterTcs.Decrease();
            }, TaskContinuationOptions.ExecuteSynchronously);
            return ret;
        }
    }
}
