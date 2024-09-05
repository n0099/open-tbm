import type { Instance } from 'tippy.js';
import tippy from 'tippy.js';
import 'tippy.js/animations/perspective.css';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/themes/light.css';
import _ from 'lodash';

if (import.meta.client) {
    tippy.setDefaultProps({
        animation: 'perspective',
        interactive: true,
        theme: 'light',
        maxWidth: 'none'
    });
};

export default defineNuxtPlugin(nuxt => {
    nuxt.vueApp.directive<HTMLElement, string | (() => string)>('tippy', {
        mounted(el, binding) {
            el.removeAttribute('title');
            tippy([el], {
                allowHTML: true,
                appendTo: document.body,
                content: binding.value,
                plugins: [{ // https://github.com/atomiks/tippyjs/issues/826
                    fn: () => ({
                        onShow(instance) {
                            if (_.isFunction(binding.value))
                                instance.setContent(binding.value());
                        }
                    })
                }]
            });
        },
        updated(el, binding) {
            if (binding.value !== binding.oldValue)
                // eslint-disable-next-line @typescript-eslint/naming-convention
                (el as unknown as { _tippy?: Instance })._tippy?.setContent(binding.value);
        },
        unmounted(el) {
            // eslint-disable-next-line @typescript-eslint/naming-convention
            (el as unknown as { _tippy?: Instance })._tippy?.destroy();
        },
        getSSRProps: binding => ({
            title: toValue(binding.value).replaceAll('<br>', '').replaceAll(/^ +/gmu, '')
        })
    });
});
