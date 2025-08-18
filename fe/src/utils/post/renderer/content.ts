export const extractContentTexts = (content?: PostContent | null) => content
    ?.reduce((acc, i) => {
        return acc + (((): string | undefined => {
            // eslint-disable-next-line @typescript-eslint/switch-exhaustiveness-check
            switch (i.type) {
                case undefined:
                case 1:
                case 7:
                case 9:
                case 18:
                case 40:
                    return i.text;
                case 2:
                case 11:
                    return `[${i.c}]`;
                case 4:
                    return `@${i.text}`;
                default:
                    return undefined;
            }
        })() ?? '');
    }, '') ?? '';
export const toHTTPS = (rawUrl?: string): string | undefined => {
    if (rawUrl === undefined)
        return undefined;
    const url = URL.parse(rawUrl);
    if (url === null)
        return undefined;
    url.protocol = 'https:';

    return url.toString();
};
const config = useRuntimeConfig().public;

// https://github.com/n0099/open-tbm/blob/3dd6664e522a7ae74c5143bacf8c90294fcc568a/c%23/crawler/src/Tieba/Crawl/Parser/Post/ReplyParser.cs#L7
const tiebaImageFilenameRegex = /^(?:[0-9a-f]{40}|[0-9a-f]{24})$/u;
export const toTiebaImageUrl = (originSrc?: string) => {
    if (originSrc === undefined || !tiebaImageFilenameRegex.test(originSrc))
        return originSrc;
    if (config.tiebaImageProxy !== '')
        return `${config.tiebaImageProxy}/${originSrc}`;

    return `https://imgsrc.baidu.com/forum/pic/item/${originSrc}.jpg`;
};

// https://github.com/n0099/open-tbm/blob/3dd6664e522a7ae74c5143bacf8c90294fcc568a/c%23/crawler/src/Tieba/Crawl/Parser/Post/ReplyParser.cs#L59
export const extractTiebaImageFilename = (rawURL: string): string | undefined => {
    const url = new URL(rawURL);
    const urlFilename = url.pathname.split('/').at(-1);
    if (urlFilename === undefined)
        return undefined;

    return tiebaImageFilenameRegex.exec(urlFilename.split('.')[0] ?? urlFilename)?.[0];
};
export const tryExtractTiebaOutboundUrl = (rawURL?: string) => {
    const url = new URL(rawURL ?? '');
    if (url.hostname === 'tieba.baidu.com' && url.pathname === '/mo/q/checkurl')
        return url.searchParams.get('url') ?? undefined;

    return rawURL;
};
export const toTiebaEmoticonUrl = (text?: string) => {
    if (text === undefined)
        return '';
    const regexMatches = /(.+?)(\d+|$)/u.exec(text);
    if (regexMatches === null)
        return '';

    const rawEmoticon = { prefix: regexMatches[1], ordinal: regexMatches[2] };
    if (rawEmoticon.prefix === 'image_emoticon' && rawEmoticon.ordinal === '')
        rawEmoticon.ordinal = '1'; // for tieba hehe emoticon: https://tb2.bdstatic.com/tb/editor/images/client/image_emoticon1.png

    /* eslint-disable @typescript-eslint/naming-convention */
    const emoticonsIndex = {
        image_emoticon: { class: 'client', ext: 'png' }, // 泡泡(<51)/客户端新版表情(>61)
        // image_emoticon: { class: 'face', ext: 'gif', prefix: 'i_f' }, // 旧版泡泡
        'image_emoticon>51': { class: 'face', ext: 'gif', prefix: 'i_f' }, // 泡泡-贴吧十周年(51>=i<=61)
        bearchildren_: { class: 'bearchildren', ext: 'gif' }, // 贴吧熊孩子
        tiexing_: { class: 'tiexing', ext: 'gif' }, // 痒小贱
        ali_: { class: 'ali', ext: 'gif' }, // 阿狸
        llb_: { class: 'luoluobu', ext: 'gif' }, // 罗罗布
        b: { class: 'qpx_n', ext: 'gif' }, // 气泡熊
        xyj_: { class: 'xyj', ext: 'gif' }, // 小幺鸡
        ltn_: { class: 'lt', ext: 'gif' }, // 冷兔
        bfmn_: { class: 'bfmn', ext: 'gif' }, // 白发魔女
        pczxh_: { class: 'zxh', ext: 'gif' }, // 张小盒
        t_: { class: 'tsj', ext: 'gif' }, // 兔斯基
        wdj_: { class: 'wdj', ext: 'png' }, // 豌豆荚
        lxs_: { class: 'lxs', ext: 'gif' }, // 冷先森
        B_: { class: 'bobo', ext: 'gif' }, // 波波
        yz_: { class: 'shadow', ext: 'gif' }, // 影子
        w_: { class: 'ldw', ext: 'gif' }, // 绿豆蛙
        '10th_': { class: '10th', ext: 'gif' } // 贴吧十周年
    } as const;
    /* eslint-enable @typescript-eslint/naming-convention */

    const filledEmoticon = {
        ...rawEmoticon,
        ...rawEmoticon.prefix === 'image_emoticon'
            && Number(rawEmoticon.ordinal) >= 51 && Number(rawEmoticon.ordinal) <= 61
            ? emoticonsIndex['image_emoticon>51']
            : emoticonsIndex[rawEmoticon.prefix as keyof typeof emoticonsIndex]
    };

    return `https://tb2.bdstatic.com/tb/editor/images/${filledEmoticon.class}`
        + `/${filledEmoticon.prefix}${filledEmoticon.ordinal}.${filledEmoticon.ext}`;
};
