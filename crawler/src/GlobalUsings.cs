global using Autofac.Extensions.DependencyInjection;
global using Autofac.Features.Indexed;
global using Autofac;
global using Google.Protobuf;
global using LinqKit;
global using NLog.Extensions.Logging;
global using NLog;

global using Microsoft.EntityFrameworkCore.Infrastructure;
global using Microsoft.EntityFrameworkCore;
global using Microsoft.Extensions.Configuration;
global using Microsoft.Extensions.DependencyInjection;
global using Microsoft.Extensions.Hosting;
global using Microsoft.Extensions.Logging;

global using System.Collections.Concurrent;
global using System.ComponentModel.DataAnnotations;
global using System.Diagnostics;
global using System.IO.Compression;
global using System.Linq.Expressions;
global using System.Reflection;
global using System.Security.Cryptography;
global using System.Text.Json;
global using System.Text;
global using Timer = System.Timers.Timer;

global using tbm.Crawler;
global using TbClient.Api.Request;
global using TbClient.Api.Response;
global using TbClient.Post;
global using Thread = TbClient.Post.Thread;
global using TbClient.Wrapper;
global using TbClient;

global using Fid = System.UInt32;
global using FidOrPostID = System.UInt64;
global using Page = System.UInt32;
global using Tid = System.UInt64;
global using Pid = System.UInt64;
global using Time = System.UInt32;
