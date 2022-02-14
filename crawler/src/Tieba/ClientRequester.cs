using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

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
        private readonly string _clientVersion;
        private static HttpClient _http = new();
        private static readonly Random Rand = new();

        public delegate ClientRequester New(string clientVersion);

        public ClientRequester(ILogger<ClientRequester> logger, IConfiguration config,
            HttpClient http, ClientRequesterTcs clientRequesterTcs, string clientVersion)
        {
            _logger = logger;
            _config = config.GetSection("ClientRequester");
            _clientRequesterTcs = clientRequesterTcs;
            _clientVersion = clientVersion;
            _http = http;
        }

        public Task<HttpResponseMessage> Post(string url, Dictionary<string, string> data)
        {
            Dictionary<string, string> clientInfo = new()
            {
                {"_client_id", $"wappc_{Rand.NextLong(1000000000000, 9999999999999)}_{Rand.Next(100, 999)}"},
                {"_client_type", "2"},
                {"_client_version", _clientVersion}
            };
            var postData = clientInfo.Concat(data).ToList();
            var sign = postData.Aggregate("", (acc, i) =>
            {
                acc += i.Key + '=' + i.Value;
                return acc;
            }) + "tiebaclient!!!";
            var signMd5 = BitConverter.ToString(MD5.HashData(Encoding.UTF8.GetBytes(sign))).Replace("-", "");
            postData.Add(KeyValuePair.Create("sign", signMd5));

            _clientRequesterTcs.Wait();
            var res = _http.PostAsync(url, new FormUrlEncodedContent(postData));
            if (_config.GetValue("LogTrace", false)) _logger.LogTrace("POST {} {}", url, data);
            res.ContinueWith(i =>
            {
                if (i.Result.IsSuccessStatusCode) _clientRequesterTcs.Increase();
                else _clientRequesterTcs.Decrease();
            });
            return res;
        }
    }
}
