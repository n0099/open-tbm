import type { FunctionalComponent, VNode } from 'vue';

// https://stackoverflow.com/questions/57962781/inject-render-method-into-template/77975171#77975171
interface Props { renderer: VNode }
const RenderFunction: FunctionalComponent<Props> = (props: Props) => props.renderer;
RenderFunction.props = { renderer: { type: Object, required: true } };
export default RenderFunction;
