<template>
<div>
    <span>页游标 </span>
    <template v-for="([cursor, decoded], index) in Object.entries(decodedCursor)" :key="cursor">
        <code v-tippy="tippyContent(decoded)" class="user-select-all">{{ cursor }}</code>
        <span v-if="Object.keys(decodedCursor).length !== index + 1" class="font-monospace text-muted">,</span>
    </template>
</div>
</template>

<script setup lang="ts">
import 'core-js/actual/typed-array/from-base64';

const { encodedCursor } = defineProps<{ encodedCursor: Cursor }>();
type DecodedCursorPart = Record<Cursor, { description: PostIDStr | `${PostTypeText}的排序字段值`, decoded: string | bigint }>;
const tippyContent = (decoded: ObjValues<DecodedCursorPart>) =>
    `<div class="d-flex align-items-center">${decoded.description}：<code class="user-select-all">${decoded.decoded}</code></div>`;

declare global {
    interface Uint8ArrayConstructor {

        // https://github.com/tc39/proposal-arraybuffer-base64
        // https://github.com/microsoft/TypeScript/pull/61696
        // eslint-disable-next-line @typescript-eslint/method-signature-style
        fromBase64(
            string: string,
            options?: { // https://github.com/zloirock/core-js/blob/e10fa8dca0f5cea568b48e33e65fd11b06443b52/packages/core-js/internals/uint8-from-base64.js#L73-L80
                alphabet?: 'base64' | 'base64url',
                lastChunkHandling?: 'loose' | 'strict' | 'stop-before-partial'
            },
        ): Uint8Array<ArrayBuffer>
    }
}
const decodeCursor = (cursor: Cursor): DecodedCursorPart | ObjEmpty => { // https://github.com/n0099/open-tbm/blob/d37cd67974090ed3e64d1e5243b7474802a7431d/be/src/PostsQuery/CursorCodec.php#L22
    if (cursor === '')
        return {};
    const decodePart = (part: string) => {
        try { // https://stackoverflow.com/questions/16245767/creating-a-blob-from-a-base64-string-in-javascript/79665302#79665302
            return Uint8Array.fromBase64(part, { alphabet: 'base64url' })

                // https://stackoverflow.com/questions/24288111/why-does-32-not-result-in-0-in-javascript/79665336#79665336
                // https://stackoverflow.com/questions/7334832/are-addition-and-bitwise-or-the-same-in-this-case
                // eslint-disable-next-line no-bitwise
                .reduce((acc, uint8, index) => acc | (BigInt(uint8) << BigInt(index * 8)), BigInt(0));
        } catch (e: unknown) {
            if (e instanceof SyntaxError) // https://tc39.es/proposal-arraybuffer-base64/spec/#sec-frombase64
                return part;

            throw e;
        }
    };
    const parts = cursor.split(',');
    if (parts.length !== 6)
        throw new Error('Cursor should have six parts.');

    return Object.assign({}, ...parts.map((part, index): DecodedCursorPart =>
        ({ [part]: { description: index % 2 === 0 ? postID[index / 2] : `${postTypeText[(index - 1) / 2]}的排序字段值`, decoded: decodePart(part) } })));
};
const decodedCursor = computed(() => decodeCursor(encodedCursor));
</script>
