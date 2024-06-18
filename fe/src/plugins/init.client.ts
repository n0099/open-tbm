import nprogress from 'nprogress';

export default defineNuxtPlugin(nuxt => {
    nuxt.hook('app:created', async () => {
        nprogress.configure({ trickleSpeed: 200 });

        if (import.meta.dev) {
            // @ts-expect-error too small to write a .d.ts for it
            await import('@/stats');
            // @ts-expect-error too small to write a .d.ts for it
            await import('@/checkCSS');
        }

        const reCAPTCHASiteKey = config.recaptchaSiteKey;
        if (reCAPTCHASiteKey !== '') {
            const tag = document.createElement('script');
            tag.async = true;
            tag.src = `https://www.recaptcha.net/recaptcha/api.js?render=${reCAPTCHASiteKey}`;
            document.body.append(tag);
        }

        const googleAnalyticsMeasurementId = config.gaMeasurementID;
        if (googleAnalyticsMeasurementId !== '') {
            const tag = document.createElement('script');
            tag.async = true;
            tag.src = `https://www.googletagmanager.com/gtag/js?id=${googleAnalyticsMeasurementId}`;
            document.body.append(tag);
            // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
            // @ts-ignore
            await import('@/gtag');
        }
    });
});
