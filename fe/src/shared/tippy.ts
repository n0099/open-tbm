import tippy, { createSingleton } from 'tippy.js';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/themes/light.css';
import 'tippy.js/animations/perspective.css';

export const initialTippy = () => createSingleton(tippy('[data-tippy-content]'), {
    allowHTML: true, interactive: true, maxWidth: 'none', theme: 'light', animation: 'perspective', appendTo: document.body
});
