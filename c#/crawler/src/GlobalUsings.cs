#pragma warning disable SA1209 // Using alias directives should be placed after other using directives
#pragma warning disable SA1210 // Using directives should be ordered alphabetically by namespace
#pragma warning disable SA1211 // Using alias directives should be ordered alphabetically by alias name
global using System.Collections.Concurrent;
global using System.ComponentModel.DataAnnotations;
global using System.ComponentModel.DataAnnotations.Schema;
global using System.Data;
global using System.Diagnostics;
global using System.Diagnostics.CodeAnalysis;
global using System.Linq.Expressions;
global using System.Reflection;
global using System.Text.Json;
global using System.Text.Json.Serialization;
global using System.Text.RegularExpressions;
global using Timer = System.Timers.Timer;

global using Autofac;
global using Autofac.Features.Indexed;
global using Autofac.Features.OwnedInstances;
global using CommunityToolkit.Diagnostics;
global using Google.Protobuf;
global using Google.Protobuf.Collections;
global using Humanizer;
global using Humanizer.Localisation;
global using LinqToDB;
global using LinqToDB.EntityFrameworkCore;
global using Microsoft.EntityFrameworkCore;
global using Microsoft.Extensions.Configuration;
global using Microsoft.Extensions.Hosting;
global using Microsoft.Extensions.Logging;
global using NSonic;
global using SuperLinq;

global using tbm.Crawler.Db;
global using tbm.Crawler.Db.Post;
global using tbm.Crawler.Db.Revision;
global using tbm.Crawler.Tieba;
global using tbm.Crawler.Tieba.Crawl;
global using tbm.Crawler.Tieba.Crawl.Crawler;
global using tbm.Crawler.Tieba.Crawl.Facade;
global using tbm.Crawler.Tieba.Crawl.Parser;
global using tbm.Crawler.Tieba.Crawl.Saver;
global using tbm.Crawler.Worker;
global using tbm.Shared;

global using TbClient.Api.Request;
global using TbClient.Api.Response;
global using TbClient.Post;
global using TbClient.Post.Common;
global using TbClient.Wrapper;
global using Thread = TbClient.Post.Thread;
global using User = tbm.Crawler.Db.User;

global using Fid = System.UInt32;
global using PostId = System.UInt64;
global using Tid = System.UInt64;
global using Pid = System.UInt64;
global using Time = System.UInt32;
global using Page = System.UInt32;
global using FailureCount = System.UInt16;
global using NullFieldsBitMask = System.UInt16;
