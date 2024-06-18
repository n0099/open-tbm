import nprogress from 'nprogress';

export default defineNuxtPlugin(nuxt => {
    nuxt.hook('app:created', async () => {
        nprogress.configure({ trickleSpeed: 200 });

        if (import.meta.env.DEV) {
            await import('@/src/stats');
            await import('@/src/checkCSS');
        }

        const reCAPTCHASiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY;
        if (reCAPTCHASiteKey !== '') {
            const tag = document.createElement('script');
            tag.async = true;
            tag.src = `https://www.recaptcha.net/recaptcha/api.js?render=${reCAPTCHASiteKey}`;
            document.body.append(tag);
        }

        const googleAnalyticsMeasurementId = import.meta.env.VITE_GA_MEASUREMENT_ID;
        if (googleAnalyticsMeasurementId !== '') {
            const tag = document.createElement('script');
            tag.async = true;
            tag.src = `https://www.googletagmanager.com/gtag/js?id=${googleAnalyticsMeasurementId}`;
            document.body.append(tag);
            // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
            // @ts-ignore
            await import('@/src/gtag');
        }
    });
});
