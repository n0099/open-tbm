window.dataLayer ||= [];
// eslint-disable-next-line @typescript-eslint/no-unsafe-call, prefer-rest-params, @typescript-eslint/no-unsafe-member-access, no-undef
function gtag() { dataLayer.push(arguments) }
gtag('js', new Date());

const config = useRuntimeConfig();
gtag('config', config.gaMeasurementID);
