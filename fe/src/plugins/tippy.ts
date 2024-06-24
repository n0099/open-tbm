import tippy, { createSingleton } from 'tippy.js';
import 'tippy.js/animations/perspective.css';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/themes/light.css';

tippy.setDefaultProps({
    animation: 'perspective',
    interactive: true,
    theme: 'light',
    maxWidth: 'none'
});

export default defineNuxtPlugin(nuxt => {
    nuxt.vueApp.directive<HTMLElement, string>('tippy', {
        mounted(el, binding) {
            el.dataset.tippyContent = binding.value;
            createSingleton(tippy([el]), { allowHTML: true, appendTo: document.body });
        },
        getSSRProps: binding => ({
            title: binding.value.replaceAll('<br>', '').replaceAll(/^ +/gmu, '')
        })
    });
});
