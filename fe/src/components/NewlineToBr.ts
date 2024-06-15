import type { FunctionalComponent, VNode } from 'vue';
import { h } from 'vue';

interface Props { is: Type, text?: string }
type Type = Parameters<typeof h>[0];

const NewlineToBr: FunctionalComponent<Props> = (props: Props, ctx) =>

    // https://github.com/inouetakuya/vue-nl2br/blob/bdcbed79c6caf011c90258184555730683318f33/src/Nl2br.ts#L29
    h(props.is, ctx.attrs, props.text
        .split('\n'))
        // eslint-disable-next-line unicorn/no-array-reduce
        .reduce((acc: Array<VNode | string>, string: string) => {
            if (acc.length === 0)
                return [string];

            return [...acc, h('br'), string];
        }, []);
NewlineToBr.props = {
    is: { type: Object, required: true },
    text: { type: String, required: false, default: '' }
};
export default NewlineToBr;
