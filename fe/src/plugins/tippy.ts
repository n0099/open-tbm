import type { Instance } from 'tippy.js';
import tippy, { createSingleton } from 'tippy.js';
import 'tippy.js/animations/perspective.css';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/themes/light.css';

if (import.meta.client) {
    tippy.setDefaultProps({
        animation: 'perspective',
        interactive: true,
        theme: 'light',
        maxWidth: 'none'
    });
};

export default defineNuxtPlugin(nuxt => {
    nuxt.vueApp.directive<HTMLElement, string>('tippy', {
        mounted(el, binding) {
            el.dataset.tippyContent = binding.value;
            el.removeAttribute('title');
            createSingleton(tippy([el]), { allowHTML: true, appendTo: document.body });
        },
        updated(el, binding) {
            if (binding.value !== binding.oldValue)
                // eslint-disable-next-line @typescript-eslint/naming-convention
                (el as unknown as { _tippy: Instance })._tippy.setContent(binding.value);
        },
        unmounted(el) {
            // eslint-disable-next-line @typescript-eslint/naming-convention
            (el as unknown as { _tippy: Instance })._tippy.destroy();
        },
        getSSRProps: binding => ({
            title: binding.value.replaceAll('<br>', '').replaceAll(/^ +/gmu, '')
        })
    });
});
