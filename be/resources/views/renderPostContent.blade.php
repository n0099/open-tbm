<?php
use Spatie\Regex\Regex;

$replaceWithHttps = static fn (string $url) => str_replace('http://', 'https://', $url);
$getImageUrl = static fn (string $hash) => "https://imgsrc.baidu.com/forum/pic/item/$hash.jpg";

/* @var array<\TbClient\Post\Common\Content> $content */
try {
?>
@spaceless
    @foreach ($content as $item)
        @switch ($item->getType())
            @case (0) {{--文本 {"text": "content\n", "type": "0"} --}}
                <span>{!! nl2br(htmlspecialchars(trim($item->getText(), "\n"))) !!}</span>
                @break
            @case (1)
                {{--链接
                    {"link": "http://tieba.baidu.com", "text": "http://tieba.baidu.com/p/", "type": "1"}
                    {"link": "http://tieba.baidu.com/mo/q/checkurl?url=", "text": "https://www.google.com", "type": "1"}
                    {"link": "http://tieba.baidu.com/mo/q/checkurl?url=", "text": "[失效] http://pan.baidu.com/s/", "type": "1", "url_type": "1"}
                    {"link": "http://tieba.baidu.com/mo/q/checkurl?url=", "text": "[有效] http://pan.baidu.com/s/", "type": "1", "url_type": "2"}
                --}}
                <?php
                    $skipCheckUrl = rawurldecode(
                        Regex::match(
                            '@^http://tieba\.baidu\.com/mo/q/checkurl\?url=(.+?)(&|$)@',
                            $item->getLink()
                        )->groupOr(1, $item->getLink())
                    );
                ?>
                <a href="{{ empty($skipCheckUrl) ? $item->getLink() : $skipCheckUrl }}" target="_blank">{{ $item->getText() }}</a>
                @break
            @case (2) {{--表情 {"c": "滑稽", "text": "image_emoticon25", "type": "2"} --}}
                <?php
                $emoticonsIndex = [
                    'image_emoticon' => ['class' => 'client', 'type' => 'png'], // 泡泡(<51)/客户端新版表情(>61)
                    // 'image_emoticon' => ['class' => 'face', 'prefix' => 'i_f', 'type' => 'gif'], // 旧版泡泡
                    'image_emoticon>51' => ['class' => 'face', 'prefix' => 'i_f', 'type' => 'gif'], // 泡泡-贴吧十周年(51>=i<=61)
                    'bearchildren_' => ['class' => 'bearchildren', 'type' => 'gif'], // 贴吧熊孩子
                    'tiexing_' => ['class' => 'tiexing', 'type' => 'gif'], // 痒小贱
                    'ali_' => ['class' => 'ali', 'type' => 'gif'], // 阿狸
                    'llb_' => ['class' => 'luoluobu', 'type' => 'gif'], // 罗罗布
                    'b' => ['class' => 'qpx_n', 'type' => 'gif'], // 气泡熊
                    'xyj_' => ['class' => 'xyj', 'type' => 'gif'], // 小幺鸡
                    'ltn_' => ['class' => 'lt', 'type' => 'gif'], // 冷兔
                    'bfmn_' => ['class' => 'bfmn', 'type' => 'gif'], // 白发魔女
                    'pczxh_' => ['class' => 'zxh', 'type' => 'gif'], // 张小盒
                    't_' => ['class' => 'tsj', 'type' => 'gif'], // 兔斯基
                    'wdj_' => ['class' => 'wdj', 'type' => 'png'], // 豌豆荚
                    'lxs_' => ['class' => 'lxs', 'type' => 'gif'], // 冷先森
                    'B_' => ['class' => 'bobo', 'type' => 'gif'], // 波波
                    'yz_' => ['class' => 'shadow', 'type' => 'gif'], // 影子
                    'w_' => ['class' => 'ldw', 'type' => 'gif'], // 绿豆蛙
                    '10th_' => ['class' => '10th', 'type' => 'gif'], // 贴吧十周年
                ];
                $emoticonRegex = Regex::match('/(.+?)(\d+|$)/', $item->getText());
                $emoticon = ['prefix' => $emoticonRegex->group(1), 'index' => $emoticonRegex->group(2)];
                if ($emoticon['prefix'] === 'image_emoticon' && $emoticon['index'] === '') {
                    $emoticon['index'] = 1; // for tieba hehe emoticon: https://tb2.bdstatic.com/tb/editor/images/client/image_emoticon1.png
                }
                $emoticon = [
                    ...$emoticon,
                    ...($emoticon['prefix'] === 'image_emoticon' && $emoticon['index'] >= 51 && $emoticon['index'] <= 61
                        ? $emoticonsIndex['image_emoticon>51']
                        : $emoticonsIndex[$emoticon['prefix']])
                ];
                $emoticonUrl = "https://tb2.bdstatic.com/tb/editor/images/{$emoticon['class']}/"
                    . "{$emoticon['prefix']}{$emoticon['index']}.{$emoticon['type']}";
                ?>
                <img referrerpolicy="no-referrer" loading="lazy"
                     src="{{ $emoticonUrl }}" alt="{{ $item->getC() }}" />
                @break
            @case (3)
                {{--图片
                    {
                        "src": "" // will be filled when displaing copied tieba emoticons
                        "size": "12345", // in byte
                        "type": "3",
                        "bsize": "800,600", // resoultion in pixel
                        "cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3Bg%3D0/sign={unknown token}/{image hash id}.jpg",
                        "origin_src": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
                        "big_cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D960%3Bq%3D60/sign={unknown token}/{image hash id}.jpg",
                        "is_long_pic": "0",
                        "origin_size": "12345", // in byte
                        "cdn_src_active": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3B/sign={unknown token}/{image hash id}.jpg",
                        "show_original_btn": "0"
                    }
                    http://imgsrc.baidu.com/forum/abpic/item/{image hash id}.jpg will shown as thumbnail
                --}}
                <img class="tieba-image" referrerpolicy="no-referrer" loading="lazy"
                     src="{{ $getImageUrl($item->getOriginSrc()) }}" />
                @break
            @case (4) {{--@用户 {"uid": "12345", "text": "(@|)username", "type": "4"} --}}
                <a href="http://tieba.baidu.com/home/main?un={{ ltrim($item->getText(), '@') }}" target="_blank">{{ $item->getText() }}</a>
                @break
            @case (5)
                {{--视频
                    external video:
                    {
                        "type": "5",
                        "text": "https://www.bilibili.com/video/av12345/"
                    }
                    tieba video:
                    {
                        "src": "http://imgsrc.baidu.com/forum/pic/item/{thumbnail hash id}.jpg", // thumbnail
                        "link": "http://tb-video.bdstatic.com/tieba-smallvideo-transcode/{video hash id}.mp4", // video source
                        "text": "http://tieba.baidu.com/mo/q/movideo/page?thumbnail={thumbnail hash id}&video={video hash id}&product=tieba-movideo&thread_id={tid}", // video play page
                        "type": "5",
                        "bsize": "1024,768", // resoultion of video
                        "count": "0",
                        "width": "1024",
                        "e_type": "15",
                        "height": "768",
                        "native_app": [],
                        "during_time": "123", // in seconds
                        "origin_size": "209767514", // in byte
                        "is_native_app": "0"
                    }
                --}}
                @if ($item->getSrc() !== '')
                    {{--
                    todo: fix anti hotlinking on domain https://tiebapic.baidu.com and http://tb-video.bdstatic.com/tieba-smallvideo-transcode
                    <video controls poster="{{ $item->getSrc() }}" src="{{ $item->getLink() }}"></video>-->
                    --}}
                    <a href="{{ $item->getText() }}" target="_blank">贴吧视频播放页</a>
                @else
                    <a href="{{ $item->getText() }}" target="_blank">
                        [[外站视频：{{ $item->getText() }}]]
                    </a>
                @endif
                @break
            @case (7) {{--换行 {"type":"7", "text":"\n"} not found in many posts --}}
                <br />
                @break
            @case (9) {{--电话 {"text": "12345678 \d{8}", "type": "9", "phonetype": "2"} --}}
                <span>{{ $item->getText() }}</span>
                @break
            @case (10)
                {{--语音
                    {
                        "type": "10",
                        "during_time": "123", // in seconds
                        "voice_md5": "{voice hash id}",
                        "is_native_app": "0",
                        "native_app": []
                    }
                --}}
                {{-- TODO: fill with voice player and play source url --}}
                <span>[[语音 {{ $item->getVoiceMd5() }} 时长:{{ $item->getDuringTime() }}s]]</span>
                @break
            @case (11)
                {{--客户端表情包
                    {
                        "c": "", // description
                        "icon": "http://tb2.bdstatic.com/tb/editor/images/faceshop/{packet id}_{packet name}/panel.png",
                        "type": "11",
                        "width": "160",
                        "height": "160",
                        "static": "http://tb2.bdstatic.com/tb/editor/images/faceshop/{packet id}_{packet name}/s_{packet id}_{packet name}_{name}.png",
                        "dynamic": "http://tb2.bdstatic.com/tb/editor/images/faceshop/{packet id}_{packet name}/d_{packet id}_{packet name}_{name}.gif",
                        "packet_name": ""
                    }
                --}}
                <img class="d-block" referrerpolicy="no-referrer" loading="lazy"
                     src="{{ $replaceWithHttps($item->getDynamic()) }}" alt="{{ $item->getC() }}" />
                @break
            @case (16)
                {{--涂鸦
                    {
                        "type": "16",
                        "bsize": "800,600", // resoultion of image
                        "graffiti_info": {
                            "url": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
                            "gid": "123456" // always 123456
                        },
                        "is_long_pic": "0",
                        "show_original_btn": "0",
                        "cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3Bg%3D0/sign={unknown token}/{image hash id}.jpg",
                        "cdn_src_active": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3B/sign={unknown token}/{image hash id}.jpg",
                        "big_cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D960%3Bq%3D60/sign={unknown token}/{image hash id}.jpg"
                    }
                --}}
                <img class="tieba-image" referrerpolicy="no-referrer" loading="lazy"
                     src="{{ $replaceWithHttps($item->getGraffitiInfo()->getUrl()) }}" alt="贴吧涂鸦" />
                @break
            @case (17) {{--活动 not found --}}
                @break
            @case (18) {{--话题 {"link": "http://tieba.baidu.com/mo/q/hotMessage?topic_id={topic id}&fid={forum id}&topic_name={topic name}", "text": "#{topic name}#", "type": "18"} --}}
                <a href="{{ $item->getLink() }}" target="_blank">{{ $item->getText() }}</a>
                @break
            @case (20)
                {{--客户端表情商店
                    {
                        "src": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
                        "type": "20",
                        "bsize": "800,600",
                        "meme_info": {
                            "width": "800",
                            "height": "600",
                            "pck_id": "0",
                            "pic_id": "{meme id}",
                            "pic_url": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
                            "thumbnail": "http://imgsrc.baidu.com/forum/abpic/item/{image hash id}.jpg",
                            "detail_link": "http://tieba.baidu.com/n/interact/emoticon/0/{meme id}?frompb=1"
                        }
                    }
                --}}
                <a href="{{ $item->getMemeInfo()->getDetailLink() }}" target="_blank">
                    <img class="tieba-image" referrerpolicy="no-referrer" loading="lazy"
                         src="{{ $replaceWithHttps($item->getSrc()) }}" />
                </a>
                @break
            @default
        @endswitch
    @endforeach
@endspaceless
<?php
} catch (Exception $e) {
    Log::channel('post-content-render')->error($e);
}
