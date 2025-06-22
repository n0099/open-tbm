import type { AnyNode } from 'domhandler';
import type { DefaultProps, Instance, Props } from 'tippy.js';
import { getChildren, getName, innerText } from 'domutils';
import { ElementType, parseDocument } from 'htmlparser2';
import tippy from 'tippy.js';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/themes/light.css';
import _ from 'lodash';

if (import.meta.client) {
    tippy.setDefaultProps({
        interactive: true,
        theme: 'light',
        maxWidth: 'none'
    });
};

const tippyInstances = new Set<Instance>(); // https://stackoverflow.com/questions/20508628/why-are-weakmaps-not-enumerable
const enableAnimation = useMediaQuery('(prefers-reduced-motion: no-preference)');
watchImmediate(enableAnimation, async () => {
    if (!import.meta.client)
        return;
    if (enableAnimation.value)
        await import('tippy.js/animations/perspective.css');
    const prop: Partial<DefaultProps> = { animation: enableAnimation.value ? 'perspective' : false };
    tippyInstances.forEach(instance => {
        instance.setProps(prop);
    });
    tippy.setDefaultProps(prop);
});

export default defineNuxtPlugin(nuxt => {
    // eslint-disable-next-line @typescript-eslint/naming-convention
    const getTippyInstance = (el: HTMLElement) => (el as { _tippy?: Instance })._tippy;
    type Content = string | (() => string);
    const contentProp = (content: Content): Partial<Props> => (_.isFunction(content)
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
            tippyInstances.add(tippy(el, {
                allowHTML: true,
                appendTo: document.body,
                ...contentProp(binding.value)
            }));
        },
        updated(el, binding) {
            if (binding.value !== binding.oldValue)
                getTippyInstance(el)?.setProps(contentProp(binding.value));
        },
        unmounted(el) {
            const instance = getTippyInstance(el);
            if (instance === undefined)
                return;
            instance.destroy();
            tippyInstances.delete(instance);
        },
        getSSRProps(binding) {
            const traverseInnerText = (node: AnyNode): string => {
                const children = getChildren(node);
                if (node.type === ElementType.Tag && getName(node) === 'br')
                    return '\n';
                if (children.length === 0)
                    return innerText(node).trim();

                return children.map(traverseInnerText).join('');
            };

            return { title: traverseInnerText(parseDocument(toValue(binding.value))) };
        }
    });
});
