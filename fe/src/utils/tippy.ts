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

export const initialTippy = (targets?: Parameters<typeof tippy>[0]) =>
    createSingleton(tippy(targets ?? '[data-tippy-content]'), {
        allowHTML: true, appendTo: document.body
    });
