import type { FunctionalComponent } from 'vue';
import _ from 'lodash';

type RootEl = Parameters<typeof h>[0];

// https://github.com/inouetakuya/vue-nl2br/blob/bdcbed79c6caf011c90258184555730683318f33/src/Nl2br.ts#L29
// https://github.com/inouetakuya/vue-nl2br/pull/276/files#diff-378b333942aed9e5ad26a422b8a16d357cbf0c1a23fd663c174fd44dd28ab6b2R6
const NewlineToBr: FunctionalComponent<{ is: RootEl, text?: string, wrapInSpan?: boolean }> =
    (props, context) =>
        h(props.is, context.attrs, (props.text ?? '')
            .split('\n')
            // eslint-disable-next-line unicorn/no-array-reduce
            .reduce((acc: Array<VNode | string>, text: string) => {
                // https://stackoverflow.com/questions/70675780/how-to-get-vue-3-render-function-h-to-output-html
                const string = props.wrapInSpan ?? false ? h('span', { textContent: text }) : text;
                if (_.isEmpty(acc))
                    return [string];

                return [...acc, h('br'), string];
            }, []));
NewlineToBr.props = {
    is: { type: [Object, String], required: true },
    text: { type: String, required: false },
    wrapInSpan: { type: Boolean, required: false }
};
export default NewlineToBr;
