import type { Int, UInt } from '@/shared';

interface NativeApp {
    isNativeApp: UInt,
    nativeApp: unknown[] // https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/NativeApp.proto
}
interface Link {
    text: string,
    link: string
}
type HasType<TType extends UInt | undefined, TSpread> = { type: TType } & Partial<TSpread>;

// https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/PbContent.proto
export type PostContent = Array<

    // 文本 {"text": "content\n", "type": "0"}
    HasType<undefined, { text?: string }> // default value 0

    // 链接
    // {"link": "http://tieba.baidu.com", "text": "http://tieba.baidu.com/p/", "type": "1"}
    // {"link": "http://tieba.baidu.com/mo/q/checkurl?url=", "text": "https://www.google.com", "type": "1"}
    // {"link": "http://tieba.baidu.com/mo/q/checkurl?url=", "text": "[失效] http://pan.baidu.com/s/", "type": "1", "url_type": "1"}
    // {"link": "http://tieba.baidu.com/mo/q/checkurl?url=", "text": "[有效] http://pan.baidu.com/s/", "type": "1", "url_type": "2"}
    | HasType<1, { urlType: Int } & Link>

    // 表情 {"c": "滑稽", "text": "image_emoticon25", "type": "2"}
    | HasType<2, {
        text: string,
        c: string
    }>

    // 图片
    // {
    //     "src": "" // will be filled when displaing copied tieba emoticons
    //     "size": "12345", // in byte
    //     "type": "3",
    //     "bsize": "800,600", // resoultion in pixel
    //     "cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3Bg%3D0/sign={unknown token}/{image hash id}.jpg",
    //     "origin_src": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
    //     "big_cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D960%3Bq%3D60/sign={unknown token}/{image hash id}.jpg",
    //     "is_long_pic": "0",
    //     "origin_size": "12345", // in byte
    //     "cdn_src_active": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3B/sign={unknown token}/{image hash id}.jpg",
    //     "show_original_btn": "0"
    // }
    // http://imgsrc.baidu.com/forum/abpic/item/{image hash id}.jpg will shown as thumbnail
    | HasType<3, {
        src: string,
        originSize: UInt,
        bsize: string,
        cdnSrc: string,
        cdnSrcActive: string,
        bigCdnSrc: string,
        originSrc: string,
        isLongPic: UInt,
        showOriginalBtn: UInt
    }>

    // @用户 {"uid": "12345", "text": "(@|)username", "type": "4"}
    | HasType<4, {
        text: string,
        uid: Int
    }>

    // 视频
    // external video:
    // {
    //     "type": "5",
    //     "text": "https://www.bilibili.com/video/av12345/"
    // }
    // tieba video:
    // {
    //     "src": "http://imgsrc.baidu.com/forum/pic/item/{thumbnail hash id}.jpg", // thumbnail
    //     "link": "http://tb-video.bdstatic.com/tieba-smallvideo-transcode/{video hash id}.mp4", // video source
    //     "text": "http://tieba.baidu.com/mo/q/movideo/page?thumbnail={thumbnail hash id}&video={video hash id}&product=tieba-movideo&thread_id={tid}", // video play page
    //     "type": "5",
    //     "bsize": "1024,768", // resoultion of video
    //     "count": "0",
    //     "width": "1024",
    //     "e_type": "15",
    //     "height": "768",
    //     "native_app": [],
    //     "during_time": "123", // in seconds
    //     "origin_size": "209767514", // in byte
    //     "is_native_app": "0"
    // }
    | HasType<5, {
        text: string,
        link: string,
        src: string,
        bsize: string,
        width: UInt,
        height: UInt,
        eType: UInt,
        count: Int,
        duringTime: UInt,
        originSize: UInt
    } & NativeApp>

    // 换行 {"type":"7", "text":"\n"} not found in many posts
    | HasType<7, { text: '\n' }>

    // 电话 {"text": "12345678 \d{8}", "type": "9", "phonetype": "2"}
    | HasType<9, {
        text: string,
        phonetype: string
    }>

    // 语音
    // {
    //     "type": "10",
    //     "during_time": "123", // in seconds
    //     "voice_md5": "{voice hash id}",
    //     "is_native_app": "0",
    //     "native_app": []
    // }
    | HasType<10, {
        duringTime: UInt,
        voiceMd5: string
    } & NativeApp>

    // 客户端表情包
    // {
    //     "c": "", // description
    //     "icon": "http://tb2.bdstatic.com/tb/editor/images/faceshop/{packet id}_{packet name}/panel.png",
    //     "type": "11",
    //     "width": "160",
    //     "height": "160",
    //     "static": "http://tb2.bdstatic.com/tb/editor/images/faceshop/{packet id}_{packet name}/s_{packet id}_{packet name}_{name}.png",
    //     "dynamic": "http://tb2.bdstatic.com/tb/editor/images/faceshop/{packet id}_{packet name}/d_{packet id}_{packet name}_{name}.gif",
    //     "packet_name": ""
    // }
    | HasType<11, {
        c: string,
        width: UInt,
        height: UInt,
        dynamic: string,
        static: string,
        packetName: string
    }>

    // 涂鸦
    // {
    //     "type": "16",
    //     "bsize": "800,600", // resoultion of image
    //     "graffiti_info": {
    //         "url": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
    //         "gid": "123456" // always 123456
    //     },
    //     "is_long_pic": "0",
    //     "show_original_btn": "0",
    //     "cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3Bg%3D0/sign={unknown token}/{image hash id}.jpg",
    //     "cdn_src_active": "http://t.hiphotos.baidu.com/forum/w%3D720%3Bq%3D60%3B/sign={unknown token}/{image hash id}.jpg",
    //     "big_cdn_src": "http://t.hiphotos.baidu.com/forum/w%3D960%3Bq%3D60/sign={unknown token}/{image hash id}.jpg"
    // }
    | HasType<16, {
        bsize: string,
        graffitiInfo: { // https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/GraffitiInfo.proto
            url: string,
            gid: Int
        },
        cdnSrc: string,
        cdnSrcActive: string,
        bigCdnSrc: string,
        isLongPic: UInt,
        showOriginalBtn: UInt
    }>

    // 活动 not found
    | HasType<17, unknown>

    // 话题
    // {
    //     "link": "http://tieba.baidu.com/mo/q/hotMessage?topic_id={topic id}&fid={forum id}&topic_name={topic name}",
    //     "text": "#{topic name}#",
    //     "type": "18"
    // }
    | HasType<18, Link>

    // 客户端表情商店
    // {
    //     "src": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
    //     "type": "20",
    //     "bsize": "800,600",
    //     "meme_info": {
    //         "width": "800",
    //         "height": "600",
    //         "pck_id": "0",
    //         "pic_id": "{meme id}",
    //         "pic_url": "http://imgsrc.baidu.com/forum/pic/item/{image hash id}.jpg",
    //         "thumbnail": "http://imgsrc.baidu.com/forum/abpic/item/{image hash id}.jpg",
    //         "detail_link": "http://tieba.baidu.com/n/interact/emoticon/0/{meme id}?frompb=1"
    //     }
    // }
    | HasType<20, {
        src: string,
        bsize: string,
        memeInfo: { // https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/MemeInfo.proto
            pckId: UInt,
            picId: UInt,
            picUrl: string,
            thumbnail: string,
            width: UInt,
            height: UInt,
            detailLink: string
        }
    }>>;
