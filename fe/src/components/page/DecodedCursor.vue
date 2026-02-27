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
const { encodedCursor } = defineProps<{ encodedCursor: Cursor }>();
type DecodedCursorPart = Record<Cursor, { description: PostIDStr | `${PostTypeText}的排序字段值` | undefined, decoded: string | bigint }>;
const tippyContent = (decoded: ObjValues<DecodedCursorPart>) =>
    `<div class="d-flex align-items-center">${decoded.description}：<code class="user-select-all">${decoded.decoded}</code></div>`;
const decodeCursor = (cursor: Cursor): DecodedCursorPart | ObjEmpty => { // https://github.com/n0099/open-tbm/blob/d37cd67974090ed3e64d1e5243b7474802a7431d/be/src/PostsQuery/CursorCodec.php#L22
    if (cursor === '')
        return {};
    const decodePart = (part: string) =>

        // https://stackoverflow.com/questions/16245767/creating-a-blob-from-a-base64-string-in-javascript/16245768#16245768
        // eslint-disable-next-line @typescript-eslint/no-misused-spread, @typescript-eslint/no-non-null-assertion
        [...atob(part.replaceAll('-', '+').replaceAll('_', '/'))].map(char => char.codePointAt(0)!)

            // https://stackoverflow.com/questions/24288111/why-does-32-not-result-in-0-in-javascript/79665336#79665336
            // https://stackoverflow.com/questions/7334832/are-addition-and-bitwise-or-the-same-in-this-case
            // eslint-disable-next-line no-bitwise
            .reduce((acc, uint8, index) => acc | (BigInt(uint8) << BigInt(index * 8)), 0n);
    const parts = cursor.split(',');
    if (parts.length !== 6)
        throw new Error('Cursor should have six parts.');

    return Object.assign({}, ...parts.map((part, index): DecodedCursorPart => ({
        [part]: {
            description: index % 2 === 0
                ? postIDs[index / 2] ?? throwError()
                : `${postTypeTexts[(index - 1) / 2] ?? throwError()}的排序字段值`,
            decoded: decodePart(part)
        }
    })));
};
const decodedCursor = computed(() => decodeCursor(encodedCursor));
</script>
