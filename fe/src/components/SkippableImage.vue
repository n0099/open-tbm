<template>
<!-- not using onload & onerror native events as they're never get triggered in this nuxt island component -->
<img v-if="imageBase64 !== null" :src="imageBase64" />
</template>

<script setup lang="ts">
const { src } = defineProps<{ src: string }>();
const emit = defineEmits<{ failToLoad: [] }>();
const fetchImageAsBase64 = async (url: string): Promise<string | null> => {
    // eslint-disable-next-line @typescript-eslint/init-declarations
    let timeoutId: NodeJS.Timeout;
    const abortController = new AbortController();

    return Promise.race([
        new Promise<null>(resolve => { timeoutId = setTimeout(() => { resolve(null) }, 5000) }),
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
    ])
        .then(resolved => {
            if (resolved === null)
                emit('failToLoad');

            return resolved;
        })
        .finally(() => { clearTimeout(timeoutId); abortController.abort() });
};
const imageBase64 = ref(await fetchImageAsBase64(src));
</script>
