@section('style-module')
    @parent
    <template id="scroll-list-template">
        <div :id="`scroll-list-${scrollListID}`"
             v-observe-visibility="{ callback: listVisibilityChanged, throttle: 100 }">
            <component :is="itemOuterTagsName" v-for="(item, itemIndex) in items" :key="itemIndex"
                       v-eval-dynamic-dimensions="shouldDisplay(itemIndex)"
                       v-observe-visibility="{ callback: itemVisibilityChanged, throttle: 100 }"
                       v-initial-item-dimensions="itemInitialDimensions"
                       v-bind="evalItemAttrs('outer', items, item, itemIndex)"
                       :data-item-index="itemIndex">
                <transition v-if="itemTransitionName != null" :name="itemTransitionName">
                    <component :is="itemInnerTagsName" v-if="shouldDisplay(itemIndex)"
                               v-bind="evalItemAttrs('inner', items, item, itemIndex)">
                        <slot :item="item"></slot>
                    </component>
                </transition>
                <template v-else>
                    <component :is="itemInnerTagsName" v-if="shouldDisplay(itemIndex)"
                               v-bind="evalItemAttrs('inner', items, item, itemIndex)">
                        <slot :item="item"></slot>
                    </component>
                </template>
            </component>
        </div>
    </template>
@endsection

@section('script-module')
    @parent
    <script src="https://cdn.jsdelivr.net/npm/vue-observe-visibility@0.4.3/dist/vue-observe-visibility.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intersection-observer@0.5.1/intersection-observer.min.js"></script>
    <script>
        'use strict';

        const scrollListComponent = Vue.component('scroll-list', {
            template: '#scroll-list-template',
            directives: {
                'eval-dynamic-dimensions': {
                    update: function (el, binding, vnode) {
                        let vue = vnode.context;
                        if (vue.$props.itemDynamicDimensions === true) {
                            let isDisplaying =  binding.value;
                            if (isDisplaying !== binding.oldValue) { // is value changed
                                if (isDisplaying) { // reset item dom's dimensions to allow user changing dom height and width
                                    el.style.height = null;
                                    el.style.width = null;
                                } else { // remain origin item dom's height and width to ensure viewport dimensions not change (height sink)
                                    let itemIndex = parseInt(el.getAttribute('data-item-index')); // fetch dimensions from previous cache
                                    let cachedItemDimensions = vue.$data.itemDOMDimensionsCache[itemIndex];
                                    el.style.height = cachedItemDimensions == null ? null : `${cachedItemDimensions.height}px`;
                                    el.style.width = cachedItemDimensions == null ? null : `${cachedItemDimensions.width}px`;
                                }
                            }
                        }
                    }
                },
                'initial-item-dimensions': {
                    bind: function (el, binding) {
                        // set initial items height and width to prevent initialed hiding item stacked at one pixel
                        el.style.height = binding.value.height;
                        el.style.width = binding.value.width;
                    }
                }
            },
            props: {
                items: {
                    type: Array,
                    required: true
                },
                itemDynamicDimensions: {
                    type: Boolean,
                    required: true
                },
                itemInitialDimensions: {
                    type: Object,
                    required: true
                },
                itemsShowingNum: {
                    type: Number,
                    required: true
                },
                itemTransitionName: String,
                itemOuterAttrs: Object,
                itemInnerAttrs: Object,
                itemOuterTags: String,
                itemInnerTags: String,
                itemObserveEvent: String,
                itemPlaceholderClass: String
            },
            data: function () {
                return {
                    displayingItemsID: [],
                    scrollListID: '',
                    itemDOMDimensionsCache: [],
                    itemEvaledAttrsCache: { outer: {}, inner: {} },
                    itemOuterTagsName: this.$props.itemOuterTags || 'div',
                    itemInnerTagsName: this.$props.itemOuterTags || 'div',
                    lastScrollTime: 0
                };
            },
            created: function () {
                // initial props and data's value with default value
                let initialDimensions = this.$props.itemInitialDimensions;
                initialDimensions.height = initialDimensions.height || '';
                initialDimensions.width = initialDimensions.width || '';
                this.$data.scrollListID = Math.random().toString(36).substring(5);
            },
            mounted: function () {
                this.$data.displayingItemsID = this.range(0, this.$props.itemsShowingNum); // initially showing first $itemsShowingNum items
            },
            methods: {
                evalItemAttrs: function (renderPosition, items, item, itemIndex) {
                    let addItemPlaceholderClass = (renderPosition, evalAttrs) => { // add itemPlaceholderClass to class attr value when hiding item
                        if (this.$props.itemPlaceholderClass != null
                            && renderPosition === 'outer'
                            && ! this.shouldDisplay(itemIndex)) {
                            evalAttrs = Object.assign({}, evalAttrs); // shadow copy to prevent mutate cache
                            if (evalAttrs.class == null) {
                                evalAttrs.class = this.$props.itemPlaceholderClass;
                            } else {
                                evalAttrs.class += ` ${this.$props.itemPlaceholderClass}`;
                            }
                        }
                        return evalAttrs
                    };
                    let cachedEvalAttrs = this.$data.itemEvaledAttrsCache[renderPosition][itemIndex];
                    if (cachedEvalAttrs == null) {
                        let itemsAttrs = renderPosition === 'outer'
                            ? this.$props.itemOuterAttrs
                            : (renderPosition === 'inner'
                                ? this.$props.itemInnerAttrs
                                : (() => { throw 'items attr render position not valid'; })());
                        let evalAttrs = {};
                        Object.keys(itemsAttrs || {}).forEach((attrName) => {
                            let itemAttrs = itemsAttrs[attrName];
                            if (itemAttrs.type === 'eval') {
                                evalAttrs[attrName] = new Function('items', 'item', 'itemIndex', `return ${itemAttrs.value}`)(items, item, itemIndex).toString();
                            } else if (itemAttrs.type === 'string') {
                                evalAttrs[attrName] = itemAttrs.value;
                            } else {
                                throw 'item attrs render type not valid';
                            }
                        });
                        this.$data.itemEvaledAttrsCache[renderPosition][itemIndex] = evalAttrs; // cache evaluated attrs value
                        return addItemPlaceholderClass(renderPosition, evalAttrs);
                    } else {
                        return addItemPlaceholderClass(renderPosition, cachedEvalAttrs);
                    }
                },
                shouldDisplay: function (itemIndex) {
                    let displayingItemsID = this.$data.displayingItemsID;
                    return itemIndex >= displayingItemsID[0] && itemIndex <= displayingItemsID[displayingItemsID.length - 1]
                },
                listVisibilityChanged: function (isVisible, observer) {
                    if (! isVisible) { // hide all items when viewport is leaving whole scroll list
                        this.$data.displayingItemsID = [];
                    }
                },
                itemVisibilityChanged: function (isVisible, observer) {
                    let itemDOM = observer.target;
                    let itemIndex = parseInt(itemDOM.getAttribute('data-item-index'));
                    if (isVisible) {
                        // moving displaying items index
                        this.$data.displayingItemsID = this.getDisplayIndexRange(0, this.$props.items.length, itemIndex, this.$props.itemsShowingNum);
                    } else {
                        // cache current hiding item dom's height and width px before hided
                        this.$data.itemDOMDimensionsCache[itemIndex] = { height: itemDOM.offsetHeight, width: itemDOM.offsetWidth };
                    }
                    // call user defined parent component event
                    let parentCompentEventName = this.$props.itemObserveEvent;
                    if (parentCompentEventName != null) {
                        this.$emit(parentCompentEventName, isVisible, observer);
                    }
                },
                range: function (start, end) { // [start, end)
                    return new Array(end - start).fill().map((d, i) => i + start);
                },
                getDisplayIndexRange: function (rangeLowerBound, rangeUpperBound, median, rangeSize) {
                    /* output example
                     * (0, 20, 0, 5) => [0, 1, 2, 3, 4]
                     * (0, 20, 1, 4) => [0, 1, 2, 3]
                     * (1, 20, 10, 4) => [9, 10, 11, 12]
                     * (1, 20, 10, 5) => [8, 9, 10, 11, 12]
                     * (1, 20, 19, 5) => [16, 17, 18, 19, 20]
                     */
                    let distanceFromMedianToRangeSize = Math.floor(rangeSize / 2); // the distance from median value to output array lower/upper bound
                    let isStartFromLowerBound = median - distanceFromMedianToRangeSize < rangeLowerBound;
                    let isEndAtUpperBound = median + distanceFromMedianToRangeSize > rangeUpperBound;
                    let out = [];
                    if (isStartFromLowerBound) {
                        out = this.range(rangeLowerBound, rangeSize); // start from rangeLowerBound will restrict output range size won't <rangeLowerBound
                    } else if (isEndAtUpperBound) {
                        out = this.range(rangeUpperBound - rangeSize + 1, rangeUpperBound + 1); // start from rangeUpperBound - rangeSize will restrict output range size won't >rangeUpperBound
                    } else {
                        out = this.range(median - distanceFromMedianToRangeSize, median + distanceFromMedianToRangeSize + 1); // normally median range
                        if (rangeSize % 2 === 0) {
                            out.shift(); // remove first lowest value to align size when required output range size is even number
                        }
                    }
                    return out;
                }
            }
        });
    </script>
@endsection