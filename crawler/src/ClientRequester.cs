using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;

namespace tbm
{
    public record ClientRequester(string ClientVersion)
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
            var signMd5 = BitConverter.ToString(MD5.Create().ComputeHash(Encoding.UTF8.GetBytes(sign))).Replace("-", "");
            postData.Add(new KeyValuePair<string, string>("sign", signMd5));

            ClientRequesterTcs.Wait();
            var res = Http.PostAsync(url, new FormUrlEncodedContent(postData));
            res.ContinueWith(i =>
            {
                if (i.Result.IsSuccessStatusCode) ClientRequesterTcs.Increase();
                else ClientRequesterTcs.Decrease();
            });
            return res;
        }
    }
}
