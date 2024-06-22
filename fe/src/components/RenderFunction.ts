// https://stackoverflow.com/questions/57962781/inject-render-method-into-template/77975171#77975171
const RenderFunction: FunctionalComponent<{ renderer: VNode }> =
    props => props.renderer;
RenderFunction.props = { renderer: { type: Object, required: true } };
export default RenderFunction;
