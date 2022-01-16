using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;

namespace tbm
{
    public class ClientRequester
    {
        private static readonly HttpClient Http = new();
        private static readonly Random Rand = new();
        private readonly string _clientVersion;

        public ClientRequester(string clientVersion) => _clientVersion = clientVersion;

        public async Task<HttpResponseMessage> Post(string url, Dictionary<string, string> data)
        {
            Dictionary<string, string> clientInfo = new()
            {
                {"_client_id", $"wappc_{Rand.NextLong(1000000000000, 9999999999999)}_{Rand.Next(100, 999)}"},
                {"_client_type", "2"},
                {"_client_version", _clientVersion}
            };
            var postData = clientInfo.Concat(data);
            var sign = postData.Aggregate("", (acc, i) =>
            {
                acc += i.Key + '=' + i.Value;
                return acc;
            }) + "tiebaclient!!!";
            var signMd5 = BitConverter.ToString(MD5.Create().ComputeHash(Encoding.UTF8.GetBytes(sign))).Replace("-", "");
            postData = postData.Append(new KeyValuePair<string, string>("sign", signMd5));
            return await Http.PostAsync(url, new FormUrlEncodedContent(postData));
        }
    }
}
