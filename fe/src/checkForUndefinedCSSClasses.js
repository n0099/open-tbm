/* eslint-disable */
// https://gist.github.com/broofa/7e95aad7ea0f34655428cda9868e7fa3
/**
 * sets up a DOM MutationObserver that watches for elements using undefined CSS
 * class names. Performance should be pretty good, but it's probably best to
 * avoid using this in production.
 *
 * Usage:
 *
 *   import cssCheck from './checkForUndefinedCSSClasses.js'
 *
 *   // Call before DOM renders (e.g. in <HEAD> or prior to React.render())
 *   cssCheck();
 */

const seen = new Set();
let defined;

function detectUndefined(node) {
    if (!node?.classList)
        return;

    node._cssChecked = true;
    for (const cl of node.classList) {
        if ([
            'grecaptcha',
            'g-recaptcha',
            'router-link-exact', // vue-router
            'nprogress',
            'noty_',
            'ls-is-cached', // lazysizes
            'tippy-',

            // own usages
            'loading',
            'echarts',
            'bs-callout',
            'statsjs',

            // fontawesome
            'fa-',
            'far',
            'fas',
            'fontawesome',
            'svg-inline--fa',

            // antdv
            'ant-',
            'anticon',
            'data-ant-cssinjs-cache-path',
            'css-dev-only-do-not-override-'
        ].some(i => cl.startsWith(i)))
            continue;

        // ignore defined and already-seen classes
        if (defined.has(cl) || seen.has(cl))
            continue;

        // mark as seen
        seen.add(cl);

        console.warn(`Unused CSS class: ${cl}`, node);
    }
}

function ingestRules(rules) {
    for (const rule of rules) {
        if (rule?.cssRules) { // rules can contain sub-rules (e.g. @media, @print)
            ingestRules(rule.cssRules);
        } else if (rule.selectorText) {
            // get defined classes
            const classes = rule.selectorText?.match(/\.[\w-]+/g);
            if (classes) {
                for (const cl of classes)
                    defined.add(cl.slice(1));
            }
        }
    }
}

export default function init() {
    if (defined)
        return defined;
    defined = new Set();

    ingestRules(document.styleSheets);

    // watch for DOM changes
    const observer = new MutationObserver(mutationsList => {
        for (const mut of mutationsList) {
            if (mut.type === 'childList' && mut.addedNodes) {
                for (const el of mut.addedNodes) {
                    if (el.nodeType === 3)
                        continue; // ignore text nodes
                    if (el.nodeType === 8)
                        continue; // ignore comment nodes
                    // check sub-dom for undefined classes
                    detectUndefined(el);
                    for (const cel of el.querySelectorAll('*'))
                        detectUndefined(cel);
                }
            } else if (mut.attributeName === 'class') {
                detectUndefined(mut.target);
            }
        }
    });

    observer.observe(document, {
        attributes: true,
        childList: true,
        subtree: true
    });
}
