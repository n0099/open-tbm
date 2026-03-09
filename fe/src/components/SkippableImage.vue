<template>
<img v-if="imageBase64 !== null" :src="imageBase64" />
</template>

<script setup lang="ts">
const { src } = defineProps<{ src: string }>();
const fetchImageAsBase64 = async (url: string): Promise<string | null> => {
    return Promise.race([
        new Promise<null>(resolve => { setTimeout(() => { resolve(null) }, 1000) }),
        (async () => {
            try {
                // https://github.com/vercel/satori/issues/626#issuecomment-2401402201
                const response = await fetch(url, { signal: abortController.signal });
                const arrayBuffer = await response.arrayBuffer();
                const base64 = Buffer.from(arrayBuffer).toString('base64');
                const type = response.headers.get('content-type');

                return `data:${type ?? 'image/png'};base64,${base64}`;
            } catch {
                return null;
            }
        })()
    ]);
};
const imageBase64 = ref(await fetchImageAsBase64(src));
</script>
