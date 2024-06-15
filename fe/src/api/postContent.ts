import type { Int, UInt } from '@/shared';
/* eslint-disable @typescript-eslint/naming-convention */

interface NativeApp {
    is_native_app: UInt,
    native_app: unknown[] // https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/NativeApp.proto
}
interface Link {
    text: string,
    link: string
}
type HasType<TType extends UInt | undefined, TSpread> = { type: TType } & Partial<TSpread>;

// https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/PbContent.proto
export type PostContent = Array<
    HasType<undefined, { text?: string }> // default value 0
    | HasType<1, { url_type: Int } & Link>
    | HasType<2, {
        text: string,
        c: string
    }>
    | HasType<3, {
        src: string,
        origin_size: UInt,
        bsize: string,
        cdn_src: string,
        cdn_src_active: string,
        big_cdn_src: string,
        origin_src: string,
        is_long_pic: UInt,
        show_original_btn: UInt
    }>
    | HasType<4, {
        text: string,
        uid: Int
    }>
    | HasType<5, {
        text: string,
        link: string,
        src: string,
        bsize: string,
        width: UInt,
        height: UInt,
        e_type: UInt,
        count: Int,
        during_time: UInt,
        origin_size: UInt
    } & NativeApp>
    | HasType<7, { text: '\n' }>
    | HasType<9, {
        text: string,
        phonetype: string
    }>
    | HasType<10, {
        during_time: UInt,
        voice_md5: string
    } & NativeApp>
    | HasType<11, {
        c: string,
        width: UInt,
        height: UInt,
        dynamic: string,
        _static: string,
        packet_name: string
    }>
    | HasType<16, {
        bsize: string,
        graffiti_info: { // https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/GraffitiInfo.proto
            url: string,
            gid: Int
        },
        cdn_src: string,
        cdn_src_active: string,
        big_cdn_src: string,
        is_long_pic: UInt,
        show_original_btn: UInt
    }>
    | HasType<17, unknown>
    | HasType<18, Link>
    | HasType<20, {
        bsize: string,
        meme_info: { // https://github.com/n0099/tbclient.protobuf/blob/7ebe461d60a24e336b930697f98cd6e3321a2aca/proto/MemeInfo.proto
            pck_id: UInt,
            pic_id: UInt,
            pic_url: string,
            thumbnail: string,
            width: UInt,
            height: UInt,
            detail_link: string
        }
    }>>;
