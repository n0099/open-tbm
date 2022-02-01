using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

namespace tbm
{
    public record ClientRequester(ILogger<ClientRequester> Logger, IConfiguration Config,
        ClientRequesterTcs ClientRequesterTcs, string ClientVersion)
    {
        public delegate ClientRequester New(string ClientVersion);

        private static readonly HttpClient Http = new();
        private static readonly Random Rand = new();

        public Task<HttpResponseMessage> Post(string url, Dictionary<string, string> data)
        {
            Dictionary<string, string> clientInfo = new()
            {
                {"_client_id", $"wappc_{Rand.NextLong(1000000000000, 9999999999999)}_{Rand.Next(100, 999)}"},
                {"_client_type", "2"},
                {"_client_version", ClientVersion}
            };
            var postData = clientInfo.Concat(data).ToList();
            var sign = postData.Aggregate("", (acc, i) =>
            {
                acc += i.Key + '=' + i.Value;
                return acc;
            }) + "tiebaclient!!!";
            var signMd5 = BitConverter.ToString(MD5.HashData(Encoding.UTF8.GetBytes(sign))).Replace("-", "");
            postData.Add(KeyValuePair.Create("sign", signMd5));

            ClientRequesterTcs.Wait();
            var res = Http.PostAsync(url, new FormUrlEncodedContent(postData));
            if (Config.GetValue("ClientRequester:LogTrace", false)) Logger.LogTrace("POST {} {}", url, data);
            res.ContinueWith(i =>
            {
                if (i.Result.IsSuccessStatusCode) ClientRequesterTcs.Increase();
                else ClientRequesterTcs.Decrease();
            });
            return res;
        }
    }
}
