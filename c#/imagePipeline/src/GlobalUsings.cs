global using System.ComponentModel.DataAnnotations;

global using Autofac;
global using CommunityToolkit.Diagnostics;
global using Microsoft.EntityFrameworkCore;
global using Microsoft.Extensions.Configuration;
global using Microsoft.Extensions.Logging;
global using OpenCvSharp;
global using SuperLinq;

global using tbm.ImagePipeline.Consumer;
global using tbm.ImagePipeline.Db;
global using tbm.ImagePipeline.Ocr;
global using tbm.Shared;

global using ImageId = System.UInt32;
global using ImageAndBytesKeyByImageId = System.Collections.Generic.Dictionary<uint, (tbm.Shared.TiebaImage Image, byte[] Bytes)>;
