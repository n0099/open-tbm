<template>
    <div v-observe-visibility="{ callback: listVisibilityChanged, throttle: 100 }"
         :id="`scroll-list-${scrollListID}`">
        <component :is="itemOuterTagsName" v-for="(item, itemIndex) in items" :key="itemIndex"
                   v-eval-dynamic-dimensions="shouldDisplay(itemIndex)"
                   v-observe-visibility="{ callback: itemVisibilityChanged, throttle: 100 }"
                   v-initial-item-dimensions="itemInitialDimensions"
                   v-bind="evalItemAttrs('outer', items, item, itemIndex)"
                   :data-item-index="itemIndex">
            <transition v-if="itemTransitionName !== undefined" :name="itemTransitionName">
                <component :is="itemInnerTagsName" v-if="shouldDisplay(itemIndex)"
                           v-bind="evalItemAttrs('inner', items, item, itemIndex)">
                    <slot :item="item" :item-index="itemIndex" />
                </component>
            </transition>
            <template v-else>
                <component :is="itemInnerTagsName" v-if="shouldDisplay(itemIndex)"
                           v-bind="evalItemAttrs('inner', items, item, itemIndex)">
                    <slot :item="item" :item-index="itemIndex" />
                </component>
            </template>
        </component>
    </div>
</template>

<script lang="ts">
import { defineComponent, onMounted, reactive } from 'vue';

export default defineComponent({
    directives: {
        'eval-dynamic-dimensions': {
            update(el, binding, vnode) {
                const vue = vnode.context;
                if (vue.$props.itemDynamicDimensions === true) {
                    const isDisplaying = binding.value;
                    if (isDisplaying !== binding.oldValue) { // is value changed
                        if (isDisplaying) { // reset item dom's dimensions to allow user changing dom height and width
                            el.style.height = null;
                            el.style.width = null;
                        } else { // remain origin item dom's height and width to ensure viewport dimensions not change (height sink)
                            const itemIndex = parseInt(el.getAttribute('data-item-index')); // fetch dimensions from previous cache
                            const cachedItemDimensions = vue.$data.itemDomDimensionsCache[itemIndex];
                            el.style.height = cachedItemDimensions === undefined ? null : `${cachedItemDimensions.height}px`;
                            el.style.width = cachedItemDimensions === undefined ? null : `${cachedItemDimensions.width}px`;
                        }
                    }
                }
            }
        },
        'initial-item-dimensions': {
            bind(el, binding) {
                // set initial items height and width to prevent initialed hiding item stacked at one pixel
                el.style.height = binding.value.height;
                el.style.width = binding.value.width;
            }
        }
    },
    props: {
        items: { type: Array, required: true },
        itemDynamicDimensions: { type: Boolean, required: true },
        itemInitialDimensions: { type: Object, required: true },
        itemMinDisplayNum: { type: Number, required: true },
        itemTransitionName: String,
        itemOuterAttrs: Object,
        itemInnerAttrs: Object,
        itemOuterTags: String,
        itemInnerTags: String,
        itemObserveEvent: String,
        itemPlaceholderClass: String
    },
    setup(props, { emit }) {
        const state = reactive({
            newDisplayItemsIndex: [],
            scrollListID: '',
            itemDomDimensionsCache: [],
            itemEvaledAttrsCache: { outer: {}, inner: {} },
            itemOuterTagsName: props.itemOuterTags || 'div',
            itemInnerTagsName: props.itemOuterTags || 'div',
            currentDisplayingItemsIndex: []
        });
        const evalItemAttrs = (renderPosition, items, item, itemIndex) => {
            const addItemPlaceholderClass = (renderPosition, evalAttrs) => { // add itemPlaceholderClass to class attr value when hiding item
                if (props.itemPlaceholderClass !== undefined
                    && renderPosition === 'outer'
                    && !shouldDisplay(itemIndex)) {
                    evalAttrs = { ...evalAttrs }; // shallow copy to prevent mutate cache
                    if (evalAttrs.class === undefined) evalAttrs.class = props.itemPlaceholderClass;
                    else evalAttrs.class += ` ${props.itemPlaceholderClass}`;
                }
                return evalAttrs;
            };
            const cachedEvalAttrs = state.itemEvaledAttrsCache[renderPosition][itemIndex];
            if (cachedEvalAttrs === undefined) {
                const itemsAttrs = renderPosition === 'outer'
                    ? props.itemOuterAttrs
                    : renderPosition === 'inner'
                        ? props.itemInnerAttrs
                        : (() => { throw 'items attr render position is invalid' })();
                const evalAttrs = {};
                Object.keys(itemsAttrs || {}).forEach(attrName => {
                    const itemAttrs = itemsAttrs[attrName];
                    if (itemAttrs.type === 'eval') evalAttrs[attrName] = new Function('items', 'item', 'itemIndex', `return ${itemAttrs.value}`)(items, item, itemIndex).toString();
                    else if (itemAttrs.type === 'string') evalAttrs[attrName] = itemAttrs.value;
                    else throw 'item attrs render type is invalid';
                });
                state.itemEvaledAttrsCache[renderPosition][itemIndex] = evalAttrs; // cache evaluated attrs value
                return addItemPlaceholderClass(renderPosition, evalAttrs);
            }
            return addItemPlaceholderClass(renderPosition, cachedEvalAttrs);
        };
        const shouldDisplay = itemIndex => state.newDisplayItemsIndex.includes(itemIndex);
        const listVisibilityChanged = (isVisible, observer) => {
            if (!isVisible) { // hide all items when viewport is leaving whole scroll list
                state.newDisplayItemsIndex = [];
            }
        };
        const itemVisibilityChanged = (isVisible, observer) => {
            const itemDom = observer.target;
            const itemIndex = parseInt(itemDom.getAttribute('data-item-index'));
            if (isVisible) {
                const newDisplayItemsID = getDisplayIndexRange(0, props.items.length, itemIndex, props.itemMinDisplayNum);
                state.currentDisplayingItemsIndex.push(itemIndex); // make sure remain current displaying items
                state.newDisplayItemsIndex = newDisplayItemsID.concat(state.currentDisplayingItemsIndex); // move newly display items index
            } else {
                state.currentDisplayingItemsIndex = !state.currentDisplayingItemsIndex.includes(itemIndex); // remove from currentDisplayingItemsIndex
                if (props.itemDynamicDimensions === true) { // cache current hiding item dom's height and width px before hided
                    state.itemDomDimensionsCache[itemIndex] = { height: itemDom.offsetHeight, width: itemDom.offsetWidth };
                }
            }

            // emit user defined parent component event
            if (props.itemObserveEvent !== undefined) emit(props.itemObserveEvent, isVisible, observer);
        };
        const range = (start, end) => // [start, end)
            new Array(end - start).fill().map((d, i) => i + start);

        const getDisplayIndexRange = (rangeLowerBound, rangeUpperBound, median, rangeSize) => {
            /*
             * output example
             * (0, 20, 0, 5) => [0, 1, 2, 3, 4]
             * (0, 20, 1, 4) => [0, 1, 2, 3]
             * (1, 20, 10, 4) => [9, 10, 11, 12]
             * (1, 20, 10, 5) => [8, 9, 10, 11, 12]
             * (1, 20, 19, 5) => [16, 17, 18, 19, 20]
             */
            const distanceFromMedianToRangeSize = Math.floor(rangeSize / 2); // the distance from median value to output array lower/upper bound
            const isStartFromLowerBound = median - distanceFromMedianToRangeSize < rangeLowerBound;
            const isEndAtUpperBound = median + distanceFromMedianToRangeSize > rangeUpperBound;
            let out = [];
            if (isStartFromLowerBound) {
                out = range(rangeLowerBound, rangeSize); // start from rangeLowerBound will restrict output range size won't <rangeLowerBound
            } else if (isEndAtUpperBound) {
                out = range(rangeUpperBound - rangeSize + 1, rangeUpperBound + 1); // start from rangeUpperBound - rangeSize will restrict output range size won't >rangeUpperBound
            } else {
                out = range(median - distanceFromMedianToRangeSize, median + distanceFromMedianToRangeSize + 1); // normally median range
                if (rangeSize % 2 === 0) out.shift(); // remove first lowest value to align size when required output range size is even number
            }
            return out;
        };

        // initial props and data's value with default value
        const initialDimensions = props.itemInitialDimensions;
        initialDimensions.height = initialDimensions.height || '';
        initialDimensions.width = initialDimensions.width || '';
        state.scrollListID = Math.random().toString(36).substring(5);

        onMounted(() => {
            state.newDisplayItemsIndex = range(0, props.itemMinDisplayNum); // initially display first itemMinDisplayNum items
        });
    }
});
</script>
