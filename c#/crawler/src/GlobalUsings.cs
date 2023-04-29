global using Autofac;
global using Autofac.Features.Indexed;
global using Google.Protobuf;
global using Google.Protobuf.Collections;
global using LinqKit;
global using Microsoft.EntityFrameworkCore;
global using Microsoft.Extensions.Configuration;
global using Microsoft.Extensions.Hosting;
global using Microsoft.Extensions.Logging;

global using System.Collections.Concurrent;
global using System.ComponentModel.DataAnnotations;
global using System.ComponentModel.DataAnnotations.Schema;
global using System.Diagnostics;
global using System.Security.Cryptography;
global using System.Text.Json;
global using System.Text;
global using System.Data;
global using Timer = System.Timers.Timer;

global using tbm.Shared;
global using tbm.Crawler.Db;
global using tbm.Crawler.Db.Post;
global using tbm.Crawler.Db.Revision;
global using tbm.Crawler.Worker;
global using tbm.Crawler.Tieba;
global using tbm.Crawler.Tieba.Crawl;
global using tbm.Crawler.Tieba.Crawl.Crawler;
global using tbm.Crawler.Tieba.Crawl.Facade;
global using tbm.Crawler.Tieba.Crawl.Parser;
global using tbm.Crawler.Tieba.Crawl.Saver;

global using TbClient;
global using TbClient.Wrapper;
global using TbClient.Api.Request;
global using TbClient.Api.Response;
global using TbClient.Post;
global using TbClient.Post.Common;
global using Thread = TbClient.Post.Thread;

global using Fid = System.UInt32;
global using PostId = System.UInt64;
global using Tid = System.UInt64;
global using Pid = System.UInt64;
global using Time = System.UInt32;
global using Page = System.UInt32;
global using FailureCount = System.UInt16;
