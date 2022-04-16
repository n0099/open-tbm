using System;
using System.Collections.Generic;
using System.Threading.Tasks;
using TbClient.Api.Request;
using TbClient.Api.Response;
using TbClient.Post;
using Tid = System.UInt64;
using Pid = System.UInt64;

namespace tbm.Crawler
{
    public sealed class SubReplyCrawler : BaseCrawler<SubReplyResponse, SubReply>
    {
        private readonly Tid _tid;
        private readonly Pid _pid;

        public delegate SubReplyCrawler New(Tid tid, Pid pid);

        public SubReplyCrawler(ClientRequester requester, Tid tid, Pid pid) : base(requester)
        {
            _tid = tid;
            _pid = pid;
        }

        public override Exception FillExceptionData(Exception e)
        {
            e.Data["tid"] = _tid;
            e.Data["pid"] = _pid;
            return e;
        }

        public override async Task<(SubReplyResponse, CrawlRequestFlag)[]> CrawlSinglePage(uint page) =>
            new[]
            {
                (await Requester.RequestProtoBuf<SubReplyRequest, SubReplyResponse>(
                    "http://c.tieba.baidu.com/c/f/pb/floor?cmd=302002",
                    new SubReplyRequest
                    {
                        Data = new SubReplyRequest.Types.Data
                        {
                            Kz = (long)_tid,
                            Pid = (long)_pid,
                            Pn = (int)page
                        }
                    },
                    "12.23.1.0"
                ), CrawlRequestFlag.None)
            };


        public override IList<SubReply> GetValidPosts(SubReplyResponse response)
        {
            var error = (TbClient.Error)SubReplyResponse.Descriptor.FindFieldByName("error").Accessor.GetValue(response);
            switch (error.Errorno)
            {
                case 4:
                    throw new TiebaException("Reply already deleted when crawling sub reply");
                case 28:
                    throw new TiebaException("Thread already deleted when crawling sub reply");
                default:
                    ValidateOtherErrorCode(response);
                    return EnsureNonEmptyPostList(response, 4,
                        "Sub reply list is empty, posts might already deleted from tieba");
            }
        }
    }
}
