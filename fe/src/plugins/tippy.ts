import type { Instance, Props } from 'tippy.js';
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
    // eslint-disable-next-line @typescript-eslint/naming-convention
    const tippyInstance = (el: unknown) => (el as { _tippy?: Instance })._tippy;
    type Content = string | (() => string);
    const tippyProps = (content: Content): Partial<Props> => (_.isFunction(content)
        ? {
            plugins: [{ // https://github.com/atomiks/tippyjs/issues/826
                fn: () => ({
                    onShow(instance) {
                        instance.setContent(content());
                    }
                })
            }]
        }
        : { content });

    nuxt.vueApp.directive<HTMLElement, Content>('tippy', {
        mounted(el, binding) {
            el.removeAttribute('title');
            tippy([el], {
                allowHTML: true,
                appendTo: document.body,
                ...tippyProps(binding.value)
            });
        },
        updated(el, binding) {
            if (binding.value !== binding.oldValue)
                tippyInstance(el)?.setProps(tippyProps(binding.value));
        },
        unmounted(el) {
            tippyInstance(el)?.destroy();
        },
        getSSRProps: binding => ({
            title: toValue(binding.value)
                .replaceAll('<br>', '')
                .replaceAll(/^ +/gmu, '')
        })
    });
});
